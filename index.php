<?php

use api\GoogleTableApi;
use bootstrap\EnvLoader;
use enums\UploadedCodeStatusEnum;
use repositories\BotCacheRepository;
use repositories\UserMysqlRepository;
use services\TelegramBotRuntimeFactory;
use services\TelegramUpdateDispatcher;

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

    $telegram = TelegramBotRuntimeFactory::createTelegramApi($botToken);
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
    $telegram = TelegramBotRuntimeFactory::createTelegramApi($botToken);
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
EnvLoader::load();
if (($_ENV['ENABLE_TELEGRAM_WEBHOOK'] ?? '0') === '1') {
    try {
        $runtime = TelegramBotRuntimeFactory::create();
        $update = $runtime->telegram->getWebhookUpdate();
        (new TelegramUpdateDispatcher($runtime))->dispatch($update);
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        log_dump(get_class($e) . ': ' . $e->getMessage(), 'LegacyWebhook');
        http_response_code(500);
        echo json_encode(['error' => 'telegram webhook handling failed']);
    }
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Not found']);
