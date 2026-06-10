<?php

namespace services;

use api\BackApi;
use api\TelegramBotApi;
use api\YandexDiskApi;
use bootstrap\EnvLoader;
use components\HttpClient;
use components\TelegramProxy;
use components\telegram\KeyBoardBuilder;
use components\telegram\MessageSender;
use Exception;
use repositories\BotCacheRepository;
use repositories\StepRepository;
use repositories\UserMysqlRepository;
use Telegram\Bot\Api;
use Throwable;

final class TelegramBotRuntimeFactory
{
    public static function create(): TelegramBotRuntime
    {
        EnvLoader::load();

        $backApi = new BackApi($_ENV['API_BACK']);
        $botCache = new BotCacheRepository();
        $userRepository = new UserMysqlRepository();
        $auth = new AuthorizeService($userRepository, $backApi, $botCache);

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
                log_dump('Bot data fallback: ' . $e->getMessage(), 'TelegramBotRuntimeFactory');
                $firms = require __DIR__ . '/../data/firms.php';
                $address = require __DIR__ . '/../data/address.php';
                $botCache->setBotData([
                    'firms' => $firms,
                    'address' => $address,
                ]);
            }
        }

        $botToken = $_ENV['BOT_TOKEN'] ?? null;
        $diskToken = $_ENV['DISK_TOKEN'] ?? null;
        if ($botToken === null || $botToken === '') {
            throw new Exception('BotToken not defined');
        }
        if ($diskToken === null || $diskToken === '') {
            throw new Exception('DiskToken not defined');
        }

        $telegram = self::createTelegramApi($botToken);
        $redis = new StepRepository();
        $messages = require __DIR__ . '/../messages/telegram.php';
        $images = require __DIR__ . '/../data/images.php';
        $notes = require __DIR__ . '/../data/notes.php';
        $sender = new MessageSender($telegram);
        $bot = new TelegramBotApi(
            telegram: $telegram,
            keyboardBuilder: new KeyBoardBuilder(),
            firms: $firms,
            address: $address,
            images: $images,
            notes: $notes,
            messages: $messages,
            sender: $sender
        );

        $apiDisk = new YandexDiskApi($diskToken);
        return new TelegramBotRuntime(
            telegram: $telegram,
            bot: $bot,
            redis: $redis,
            apiDisk: $apiDisk,
            auth: $auth,
            backApi: $backApi,
            botToken: $botToken,
            telegramProxy: self::getTelegramProxy(),
            telegramProxyType: self::getTelegramProxyType(),
            firms: $firms,
        );
    }

    public static function createTelegramApi(string $botToken): Api
    {
        $proxyOptions = TelegramProxy::buildGuzzleOptions(self::getTelegramProxy(), self::getTelegramProxyType());
        $telegram = new Api($botToken);
        $telegram->setHttpClientHandler(new HttpClient($proxyOptions));
        return $telegram;
    }

    public static function getTelegramProxy(): string
    {
        $proxy = $_ENV['TELEGRAM_PROXY'] ?? null;
        if (!$proxy) {
            throw new Exception('TELEGRAM_PROXY not defined');
        }
        return $proxy;
    }

    public static function getTelegramProxyType(): ?string
    {
        $proxyType = $_ENV['TELEGRAM_PROXY_TYPE'] ?? null;
        return $proxyType !== '' ? $proxyType : null;
    }
}
