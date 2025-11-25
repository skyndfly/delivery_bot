<?php

use api\BackApi;
use api\YandexDiskApi;
use api\GoogleTableApi;
use api\TelegramBotApi;
use bootstrap\EnvLoader;
use components\telegram\KeyBoardBuilder;
use components\telegram\MessageSender;
use handler\CallbackQuery;
use handler\MessageHandler;
use repositories\CompanyRepository;
use repositories\StepRepository;
use repositories\UserMysqlRepository;
use services\AuthorizeService;
use services\Company\GetCachedCompanyService;
use Telegram\Bot\Api;

require_once "vendor/autoload.php";
require_once 'helpers/functions.php';

try {
    EnvLoader::load();

    $table = new GoogleTableApi($_ENV['TABLE_URL']);

    $userRepository = new UserMysqlRepository();
    try {
        $companyRepository = new CompanyRepository();
        $getCachedCompanyService = new GetCachedCompanyService($companyRepository);
        $firms = $getCachedCompanyService->execute();
    }catch (Throwable){
        $firms = require_once 'data/firms.php';
    }

    $auth = new AuthorizeService($userRepository);
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
    $backApi = new BackApi($_ENV['API_BACK']);

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
