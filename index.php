<?php

use classes\ApiYandexDisk;
use classes\StepStorage;
use classes\TelegramBot;
use Dotenv\Dotenv;
use enums\StateEnum;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\CallbackQuery;

require_once "vendor/autoload.php";
require_once 'helpers/functions.php';

try {

    $firms = require_once 'data/firms.php';
    $address = require_once 'data/address.php';
    $images = require_once 'data/images.php';
    $notes = require_once 'data/notes.php';


    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();

    $botToken = $_ENV['BOT_TOKEN'] ?? null;

    $telegram = new Api($botToken);
    $redis = new StepStorage();

    $bot = new TelegramBot(
        $telegram,
        $firms,
        $address,
        $images,
        $notes
    );
    $apiDisk = new ApiYandexDisk();

    $update = $telegram->getWebhookUpdate();
    $message = $update->getMessage();
    $currentDate = (new DateTimeImmutable())->format('d-m-Y');
    if ($message) {
        $chatId = $message->getChat()->getId();
        $text = $message->getText();
        if ($text == "/start") {
            $apiDisk->createFolder($currentDate);
            $bot->actionStart($chatId);
            $redis->setStep($chatId, StateEnum::FIRM_SELECT->value);
        } else if ($text == "/restart") {
            $apiDisk->createFolder($currentDate);
            $bot->actionStart($chatId);
            $redis->setStep($chatId, StateEnum::FIRM_SELECT->value);
        } else if ($message->has('photo') && $redis->getStep($chatId) === StateEnum::AWAITING_PHOTO->value) {
            $photos = $message->getPhoto();
            $url = $bot->getImagePath($photos, $botToken);
            if (!$apiDisk->uploadFile($url, $redis->getPath($chatId))) {
                $bot->actionBadUpload($chatId);
            }else{
                $bot->actionSuccessDownload($chatId);
                $bot->actionStart($chatId);
                $redis->setStep($chatId, StateEnum::FIRM_SELECT->value);
            }


        }
    }
    /** @var CallbackQuery|null $callbackQuery */
    $callbackQuery = $update->get('callback_query');
    if ($callbackQuery) {
        $chatId = $callbackQuery->getMessage()->getChat()->getId();
        $data = $callbackQuery->getData();

        if (str_starts_with($data, 'firm|')) {
            $firm = substr($data, strlen('firm|'));
            $apiDisk->createFolder($currentDate . '/' . $firms[$firm]);
            $bot->actionSelectedFirm($firm, $chatId);
            $redis->setStep($chatId, StateEnum::FIRM_SELECTED->value);
        }
        if (str_starts_with($data, 'address|')) {
            [, $firm, $address] = explode('|', $data, 3);
            $path = $currentDate . '/' . $firms[$firm] . '/' . $address;
            $apiDisk->createFolder($path);
            $bot->actionSelectedAddress($chatId);
            $redis->setPath($chatId, $path);
            $redis->setStep($chatId, StateEnum::AWAITING_PHOTO->value);
        }
    }
} catch (Exception|Error $e) {
    file_put_contents("log.txt", "Exception: {$e->getMessage()}\n", FILE_APPEND);
}
