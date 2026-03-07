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
use repositories\BotCacheRepository;
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
    $companyName = $input['companyName'] ?? null;
    $address = $input['address'] ?? null;
    $placeLabel = null;
    if (!empty($companyName) || !empty($address)) {
        $parts = array_filter([$companyName, $address], fn($value) => is_string($value) && $value !== '');
        $placeLabel = implode(' — ', $parts);
    }
    $placeLine = $placeLabel !== null ? "🏢 {$placeLabel}.\n" : '🏢 Адрес уточняется.' . "\n";
    if ($status == UploadedCodeStatusEnum::ISSUED) {
        $text = '📲 Ваш заказ отправленный ' . $placeLine.' в ' .$input['createdAt'] . ' успешно собран и бережно упакован. 

⚠️В скором времени будет доставлен по адресу:

🏢 г. Антрацит, ул. Петровского 21, 1 этаж, 108 кабинет. 

Ждем Вас✅';
    } elseif ($status == UploadedCodeStatusEnum::OUTDATED) {
        $text = '‼️ ВНИМАНИЕ ‼️

📲 Ваш код отправленный ' . $placeLine.' в ' . $input['createdAt'] . ' УСТАРЕЛ ❌

🏢 г. Антрацит, ул. Петровского 21, 1 этаж, 108 кабинет. 

Вам необходимо сделать следующие действия 👇

1⃣ Зайдите на сайт маркетплейса 

2⃣ Сделайте скрин НОВОГО КОДА 

3⃣ Повторно отправьте в БОТ 

‼️Время приёма кодов ограничено, успейте обновить код до закрытия ‼️';
    } elseif ($status == UploadedCodeStatusEnum::NOT_PAID) {
        $text = '‼️ ВНИМАНИЕ ‼️

📲 Ваш код отправленный ' . $placeLine.' в ' . $input['createdAt'] . ' НЕ ОПЛАЧЕН ❌

🏢 г. Антрацит, ул. Петровского 21, 1 этаж, 108 кабинет. 

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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['REQUEST_URI'] === '/cache/clear') {
    $cache = new BotCacheRepository();
    $cache->clearAll();
    echo json_encode(['ok' => true]);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && str_starts_with($_SERVER['REQUEST_URI'], '/users')) {
    $query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
    parse_str($query ?? '', $params);
    $phone = $params['phone'] ?? null;
    $chatId = $params['chatId'] ?? null;
    $page = isset($params['page']) ? max(1, (int) $params['page']) : 1;
    $pageSize = isset($params['pageSize']) ? max(1, (int) $params['pageSize']) : 50;
    $offset = ($page - 1) * $pageSize;
    $userRepository = new UserMysqlRepository();
    $total = $userRepository->countUsers($phone, $chatId);
    $users = $userRepository->searchUsers($phone, $chatId, $pageSize, $offset);
    header('Content-Type: application/json');
    echo json_encode([
        'users' => $users,
        'total' => $total,
        'page' => $page,
        'pageSize' => $pageSize,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['REQUEST_URI'] === '/message') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['chatId'], $input['text'])) {
        http_response_code(400);
        echo json_encode(['error' => 'chatId and text required']);
        exit;
    }
    EnvLoader::load();
    $botToken = $_ENV['BOT_TOKEN'] ?? null;
    if (!$botToken) {
        http_response_code(500);
        echo json_encode(['error' => 'Bot token missing']);
        exit;
    }
    $telegram = new Api($botToken);
    $telegram->sendMessage([
        'chat_id' => $input['chatId'],
        'text' => $input['text'],
    ]);
    echo json_encode(['ok' => true]);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['REQUEST_URI'] === '/users/sync') {
    try {
        EnvLoader::load();
        $table = new GoogleTableApi($_ENV['TABLE_URL']);
        $userRepository = new UserMysqlRepository();
        $service = new \services\UserSyncService(
            userRepository: $userRepository,
            googleTableApi: $table,
        );
        $service->handle();
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
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
    $botCache = new BotCacheRepository();
    $cachedBotData = $botCache->getBotData();
    if ($cachedBotData !== null) {
        $firms = $cachedBotData['firms'];
        $address = $cachedBotData['address'];
    } else {
        try {
            $botData = $backApi->getBotData();
            $firms = $botData['firms'];
            $address = $botData['address'];
            $botCache->setBotData($botData);
        } catch (Throwable $e) {
            log_dump($e->getMessage());
            $firms = require_once 'data/firms.php';
            $address = require_once 'data/address.php';
            $botCache->setBotData([
                'firms' => $firms,
                'address' => $address,
            ]);
        }
    }
    //    }

    $auth = new AuthorizeService($userRepository, $backApi, $botCache);
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
