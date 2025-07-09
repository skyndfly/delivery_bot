<?php

use classes\ApiYandexDisk;
use classes\TelegramBot;
use Telegram\Bot\Api;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Objects\CallbackQuery;

require_once "vendor/autoload.php";
function log_dump($var, $title = '')
{
    ob_start();
    echo "\n--- $title ---\n";
    var_dump($var);
    echo "\n";
    file_put_contents("log.txt", ob_get_clean(), FILE_APPEND);
}

try {

    $botToken = "8000230460:AAFU0ivU-a2PVr69iWUf5K3JLc7d791Xknw";
    $telegram = new Api($botToken);

    $keys = require_once 'data/keys.php';
    $firms = require_once 'data/firms.php';
    $address = require_once 'data/address.php';
    $images = require_once 'data/images.php';
    $notes = require_once 'data/notes.php';

    $bot = new TelegramBot(
        $telegram,
        $keys,
        $firms,
        $address,
        $images,
        $notes
    );
    $apiDisk = new ApiYandexDisk();

    $update = $telegram->getWebhookUpdate();
    $message = $update->getMessage();
    $currentDate = (new DateTimeImmutable())->format('m-d-Y');
    if ($message) {
        $chat_id = $message->getChat()->getId();
        $text = $message->getText();

        if ($text == "/start") {
            $apiDisk->createFolder($currentDate);
            $bot->actionStart($chat_id);
        }
    }
    /** @var CallbackQuery|null $callbackQuery */
    $callbackQuery = $update->get('callback_query');
    if ($callbackQuery) {
        $chatId = $callbackQuery->getMessage()->getChat()->getId();
        $data = $callbackQuery->getData();

        if (str_starts_with($data, 'firm|')) {
            $firm = substr($data, strlen('firm|'));
            $apiDisk->createFolder($currentDate . '/' . $firm);
            $bot->actionSelectedFirm($firm, $chatId);

        }
        if (str_starts_with($data, 'address|')) {
            [, $firm, $address] = explode('|', $data, 3);
            $apiDisk->createFolder($currentDate . '/' . $firm . '/' . $address);
            $bot->actionSelectedAddress($chatId);
        }
    }
} catch (Exception|Error $e) {
    file_put_contents("log.txt", "Exception: {$e->getMessage()}\n", FILE_APPEND);
}