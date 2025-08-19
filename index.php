<?php

use api\YandexDiskApi;
use api\GoogleTableApi;
use api\TelegramBotApi;
use bootstrap\EnvLoader;
use components\telegram\KeyBoardBuilder;
use components\telegram\MessageSender;
use handler\CallbackQuery;
use handler\MessageHandler;
use repositories\StepRepository;
use repositories\UserRepository;
use services\AuthorizeService;
use Telegram\Bot\Api;

require_once "vendor/autoload.php";
require_once 'helpers/functions.php';

try {


    EnvLoader::load();
    $table = new GoogleTableApi($_ENV['TABLE_URL']);

    $userRepository = new UserRepository();

    $auth = new AuthorizeService($userRepository);


    $firms = require_once 'data/firms.php';
    $address = require_once 'data/address.php';
    $images = require_once 'data/images.php';
    $notes = require_once 'data/notes.php';
    $telegramMessages = require_once 'messages/telegram.php';

    $botToken = $_ENV['BOT_TOKEN'] ?? null;
    $diskToken = $_ENV['DISK_TOKEN'] ?? null;

    if ($botToken === null) {
        throw new Exception('BotToken not defined');
    }

    $telegram = new Api($botToken);
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

    $update = $telegram->getWebhookUpdate();
    $message = $update->getMessage();

    $tz = new DateTimeZone('Europe/Moscow');
    $currentDate = new DateTimeImmutable('now', $tz);


    $apiDisk->createFolder($currentDate->format('d-m-Y'));
    $handle = null;

    if (!$update->getMessage()->getFrom()->getIsBot()) {
        $handle = new MessageHandler(
            bot: $bot,
            redis: $redis,
            apiDisk: $apiDisk,
            authorize: $auth,
            botToken: $botToken,
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
    file_put_contents(
        "log.txt",
        (new DateTimeImmutable())->format('d-m-Y-H-i-s') . " Exception: {$e->getMessage()}\n" . " Code: {$e->getCode()}\n",
        FILE_APPEND
    );
}
