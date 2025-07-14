<?php

use api\ApiYandexDisk;
use api\TelegramBot;
use bootstrap\EnvLoader;
use db\StepStorage;
use handler\CallbackQuery;
use handler\MessageHandler;
use Telegram\Bot\Api;

require_once "vendor/autoload.php";
require_once 'helpers/functions.php';

try {

    $firms = require_once 'data/firms.php';
    $address = require_once 'data/address.php';
    $images = require_once 'data/images.php';
    $notes = require_once 'data/notes.php';

    EnvLoader::load();


    $botToken = $_ENV['BOT_TOKEN'] ?? null;
    $diskToken = $_ENV['DISK_TOKEN'] ?? null;

    if ($botToken === null) {
        throw new Exception('BotToken not defined');
    }

    $telegram = new Api($botToken);
    $redis = new StepStorage();

    $bot = new TelegramBot(
        $telegram,
        $firms,
        $address,
        $images,
        $notes
    );
    $apiDisk = new ApiYandexDisk($diskToken);

    $update = $telegram->getWebhookUpdate();
    $message = $update->getMessage();

    $tz = new DateTimeZone('Europe/Moscow');
    $currentDate = new DateTimeImmutable('now', $tz);


    $apiDisk->createFolder($currentDate->format('d-m-Y'));
    $handle = null;
    log_dump($currentDate->format('d-m-Y H:i:s'));
    if (!$update->getMessage()->getFrom()->getIsBot()) {
        $handle = new MessageHandler(
            bot: $bot,
            redis: $redis,
            apiDisk: $apiDisk,
            botToken: $botToken,
        );
    } elseif ($update->get('callback_query')) {
        $handle = new CallbackQuery(
            bot: $bot,
            redis: $redis,
            apiDisk: $apiDisk,
            currentDate: $currentDate,
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
