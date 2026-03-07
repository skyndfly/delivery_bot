<?php

use api\BackApi;
use api\YandexDiskApi;
use api\GoogleTableApi;
use api\TelegramBotApi;
use bootstrap\EnvLoader;
use components\HttpClient;
use components\telegram\KeyBoardBuilder;
use components\telegram\MessageSender;
use enums\UploadedCodeStatusEnum;
use GuzzleHttp\Client;
use handler\CallbackQuery;
use handler\MessageHandler;
use repositories\StepRepository;
use repositories\UserMysqlRepository;
use services\AuthorizeService;
use Telegram\Bot\Api;
use Telegram\Bot\HttpClients\GuzzleHttpClient;

require_once "vendor/autoload.php";
require_once 'helpers/functions.php';

// ---------------------
//API
// ---------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['REQUEST_URI'] === '/issued') {
    $input = json_decode(file_get_contents('php://input'), true);
    log_dump($input);

    if (!isset($input['chatId'], $input['status'])) {
        http_response_code(400);
        echo json_encode(['error' => 'chatId and status required']);
        exit;
    }

    EnvLoader::load(); // нужно, чтобы загрузился BOT_TOKEN

    $botToken = $_ENV['BOT_TOKEN'] ?? null;

    if (!$botToken) {
        http_response_code(500);
        echo json_encode(['error' => 'Bot token missing']);
        exit;
    }

    $telegram = new Api($botToken);
    $status = UploadedCodeStatusEnum::from($input['status']);
    if ($status == UploadedCodeStatusEnum::ISSUED) {
        $text = '📲 Ваш заказ отправленный ' . $input['createdAt'] . ' успешно собран и бережно упакован. 

⚠️В скором времени будет доставлен по адресу:

🏢 г. Антрацит, ул. Петровского 21, 1 этаж, 108 кабинет. 

Ждем Вас✅';
    } elseif ($status == UploadedCodeStatusEnum::OUTDATED) {
        $text = '‼️ ВНИМАНИЕ ‼️

📲 Ваш код отправленный ' . $input['createdAt'] . ' УСТАРЕЛ ❌

Вам необходимо сделать следующие действия 👇

1⃣ Зайдите на сайт маркетплейса 

2⃣ Сделайте скрин НОВОГО КОДА 

3⃣ Повторно отправьте в БОТ 

‼️Время приёма кодов ограничено, успейте обновить код до закрытия ‼️';
    } elseif ($status == UploadedCodeStatusEnum::NOT_PAID) {
        $text = '‼️ ВНИМАНИЕ ‼️

📲 Ваш код отправленный ' . $input['createdAt'] . ' НЕ ОПЛАЧЕН ❌

Вам необходимо сделать следующие действия 👇

1⃣ Зайдите на сайт маркетплейса 

2⃣ Оплатите товары самостоятельно 

3⃣Сделайте скрин НОВОГО КОДА 

4⃣ Повторно отправьте в БОТ 

‼️Время приёма кодов ограничено, успейте обновить код до закрытия ‼️';
    }
    $telegram->sendMessage([
        'chat_id' => $input['chatId'],
        'text' => $text,
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [
                    [
                        'text' => 'Добавить код ✅',
                        'callback_data' => 'action_start'
                    ]
                ]
            ]
        ])
    ]);


    echo json_encode(['ok' => true]);
    exit;
}
// ---------------------
// API
// -

try {
    EnvLoader::load();

    $table = new GoogleTableApi($_ENV['TABLE_URL']);

    $userRepository = new UserMysqlRepository();
    //    try {
    //        $companyRepository = new CompanyRepository();
    //        $getCachedCompanyService = new GetCachedCompanyService($companyRepository);
    //        $firms = $getCachedCompanyService->execute();
    //    } catch (Throwable) {
    $backApi = new BackApi($_ENV['API_BACK']);
    try {
        $botData = $backApi->getBotData();
        $firms = $botData['firms'];
        $address = $botData['address'];
    } catch (Throwable $e) {
        log_dump($e->getMessage());
        $firms = require_once 'data/firms.php';
        $address = require_once 'data/address.php';
    }
    //    }

    $auth = new AuthorizeService($userRepository, $backApi);
    if (!isset($address)) {
        $address = require_once 'data/address.php';
    }
    $images = require_once 'data/images.php';
    $notes = require_once 'data/notes.php';
    $telegramMessages = require_once 'messages/telegram.php';

    $botToken = $_ENV['BOT_TOKEN'] ?? null;
    $diskToken = $_ENV['DISK_TOKEN'] ?? null;

    if ($botToken === null) {
        throw new Exception('BotToken not defined');
    }

    //    $guzzle = new Client([
    //        'timeout' => 10,
    //        'connect_timeout' => 5,
    //        'curl' => [
    //            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
    //        ],
    //    ]);
    $telegram = new Api($botToken);

    //    $telegram->setHttpClientHandler(
    //        new HttpClient()
    //    );
    //    $telegram->setHttpClientHandler(
    //        new GuzzleHttpClient($guzzle));
    $redis = new StepRepository();
    $keyBoardBuilder = new KeyBoardBuilder();
    $telegramMessageSender = new MessageSender($telegram);
    $bot = new TelegramBotApi(
        telegram: $telegram,
        keyboardBuilder: $keyBoardBuilder,
        firms: $firms,
        address: $address,
        images: $images,
        notes: $notes,
        messages: $telegramMessages,
        sender: $telegramMessageSender
    );

    $apiDisk = new YandexDiskApi($diskToken);
    if (!isset($backApi)) {
        $backApi = new BackApi($_ENV['API_BACK']);
    }

    $update = $telegram->getWebhookUpdate();
    $message = $update->getMessage();

    $tz = new DateTimeZone('Europe/Moscow');
    $currentDate = new DateTimeImmutable('now', $tz);

    $apiDisk->createFolder($currentDate->format('d-m-Y'));
    $handle = null;

    $message = $update->getMessage();
    if ($message && $message->getFrom() && !$message->getFrom()->getIsBot()) {
        $handle = new MessageHandler(
            bot: $bot,
            redis: $redis,
            apiDisk: $apiDisk,
            authorize: $auth,
            botToken: $botToken,
            backApi: $backApi,
        );
    } elseif ($update->get('callback_query')) {
        $handle = new CallbackQuery(
            bot: $bot,
            redis: $redis,
            apiDisk: $apiDisk,
            currentDate: $currentDate,
            authorize: $auth,
            firms: $firms,
        );
    }
    if ($handle === null) {
        throw new DomainException('Handle not set');
    }
    $handle->handle($update);
} catch (Exception|Error $e) {
    log_dump($e->getMessage());
}
