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
    $currentDate = (new DateTimeImmutable())->format('d-m-Y');
    $apiDisk->createFolder($currentDate);
    if ($message && $message->getFrom()->getIsBot() === false) {
        $chatId = $message->getChat()->getId();
        $text = $message->getText();
        $step = $redis->getStep($chatId);

        if ($text === "/start" || $text === "/restart") {
            $bot->actionStart($chatId);
            $redis->setStep($chatId, StateEnum::FIRM_SELECT->value);
        } elseif (!$message->has('photo') && $step === StateEnum::AWAITING_PHOTO->value) {
            $bot->actionSendError($chatId);
        } elseif ($message->has('photo')) {
            if ($step === StateEnum::AWAITING_PHOTO->value) {
                $photos = $message->getPhoto();
                $url = $bot->getImagePath($photos, $botToken);
                if (!$apiDisk->uploadFile($url, $redis->getPath($chatId))) {
                    $bot->actionBadUpload($chatId);
                } else {
                    $bot->actionSuccessDownload($chatId);
                    $redis->setStep($chatId, StateEnum::PAUSE->value);
                }
            } else {
                $bot->actionSendError($chatId);
            }
        } elseif (!in_array($text, ['/start', '/restart']) && $step === StateEnum::FIRM_SELECT->value) {
            $bot->actionSendError($chatId);
        } elseif (!in_array($text, ['/start', '/restart']) && $step === StateEnum::FIRM_SELECTED->value) {
            $bot->actionSendError($chatId);
            // Повторно показывать адреса не получится без выбранной фирмы, поэтому лучше не повторять вывод клавиатуры здесь
        }
    }
    /** @var CallbackQuery|null $callbackQuery */
    $callbackQuery = $update->get('callback_query');
    if ($callbackQuery) {
        $chatId = $callbackQuery->getMessage()->getChat()->getId();
        $data = $callbackQuery->getData();

        if ($data === 'action_start') {
            $bot->actionStart($chatId);
            $redis->setStep($chatId, StateEnum::FIRM_SELECT->value);
            $bot->answerCallback($callbackQuery->getId());
        } elseif ($data === 'action_end') {
            $bot->actionEnd($chatId);
            $redis->setStep($chatId, StateEnum::PAUSE->value);
            $bot->answerCallback($callbackQuery->getId());
        } elseif (str_starts_with($data, 'firm|')) {
            $firm = substr($data, strlen('firm|'));
            $apiDisk->createFolder($currentDate . '/' . $firms[$firm]);
            $bot->actionSelectedFirm($firm, $chatId);
            $redis->setStep($chatId, StateEnum::FIRM_SELECTED->value);
            $bot->answerCallback($callbackQuery->getId());
        } elseif (str_starts_with($data, 'address|')) {
            [, $firm, $address] = explode('|', $data, 3);
            $path = $currentDate . '/' . $firms[$firm] . '/' . $address;
            $apiDisk->createFolder($path);
            $bot->actionSelectedAddress($chatId);
            $redis->setPath($chatId, $path);
            $redis->setStep($chatId, StateEnum::AWAITING_PHOTO->value);
            $bot->answerCallback($callbackQuery->getId());
        }
    }
} catch (Exception|Error $e) {
    file_put_contents(
        "log.txt",
        (new DateTimeImmutable())->format('d-m-Y-H-i-s') . " Exception: {$e->getMessage()}\n" . " Code: {$e->getCode()}\n",
        FILE_APPEND
    );
}
