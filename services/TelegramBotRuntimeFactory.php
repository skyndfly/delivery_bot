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
    private const DEFAULT_POLLING_TIMEOUT = 50;
    private const DEFAULT_HTTP_TIMEOUT = 30;
    private const DEFAULT_HTTP_CONNECT_TIMEOUT = 10;
    private const HTTP_TIMEOUT_SAFETY_MARGIN = 5;

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

        $telegram = self::createTelegramApi($botToken, 'runtime');
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

    public static function createTelegramApi(string $botToken, string $context = 'short-request'): Api
    {
        $proxyType = self::getTelegramProxyType();
        $proxyOptions = TelegramProxy::buildGuzzleOptions(self::getTelegramProxy(), $proxyType);
        $httpConfig = self::getTelegramHttpClientConfig();

        log_dump(
            '[INFO] Telegram API HTTP config: context=' . $context
            . ', timeout=' . $httpConfig['timeout']
            . ', connect_timeout=' . $httpConfig['connect_timeout']
            . ', polling_timeout=' . $httpConfig['polling_timeout']
            . ', proxy_type=' . ($proxyType ?? 'default'),
            'TelegramBotRuntimeFactory'
        );

        $telegram = new Api($botToken);
        $telegram->setHttpClientHandler(new HttpClient(
            guzzleConfig: $proxyOptions,
            timeout: $httpConfig['timeout'],
            connectTimeout: $httpConfig['connect_timeout'],
            context: $context
        ));
        return $telegram;
    }

    public static function getTelegramHttpClientConfig(): array
    {
        $pollingTimeout = self::envPositiveInt('TELEGRAM_POLLING_TIMEOUT', self::DEFAULT_POLLING_TIMEOUT);
        $minimumHttpTimeout = $pollingTimeout + self::HTTP_TIMEOUT_SAFETY_MARGIN;
        $defaultHttpTimeout = max(self::DEFAULT_HTTP_TIMEOUT, $minimumHttpTimeout);
        $configuredHttpTimeout = self::envOptionalPositiveInt('TELEGRAM_HTTP_TIMEOUT');
        $httpTimeout = $configuredHttpTimeout ?? $defaultHttpTimeout;
        $httpTimeoutAdjusted = false;
        $connectTimeout = self::envPositiveInt(
            'TELEGRAM_HTTP_CONNECT_TIMEOUT',
            self::DEFAULT_HTTP_CONNECT_TIMEOUT
        );

        if ($httpTimeout <= $pollingTimeout) {
            $httpTimeoutAdjusted = true;
            log_dump(
                '[WARN] TELEGRAM_HTTP_TIMEOUT=' . $httpTimeout
                . ' is not greater than TELEGRAM_POLLING_TIMEOUT=' . $pollingTimeout
                . '; using ' . $minimumHttpTimeout,
                'TelegramBotRuntimeFactory'
            );
            $httpTimeout = $minimumHttpTimeout;
        }

        return [
            'timeout' => $httpTimeout,
            'connect_timeout' => $connectTimeout,
            'polling_timeout' => $pollingTimeout,
            'configured_timeout' => $configuredHttpTimeout,
            'timeout_adjusted' => $httpTimeoutAdjusted,
        ];
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

    private static function envPositiveInt(string $name, int $default): int
    {
        $value = $_ENV[$name] ?? null;
        if ($value === null || $value === '') {
            return $default;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($parsed === false) {
            log_dump(
                '[WARN] Invalid ' . $name . '=' . $value . '; using ' . $default,
                'TelegramBotRuntimeFactory'
            );
            return $default;
        }

        return (int) $parsed;
    }

    private static function envOptionalPositiveInt(string $name): ?int
    {
        $value = $_ENV[$name] ?? null;
        if ($value === null || $value === '') {
            return null;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($parsed === false) {
            log_dump(
                '[WARN] Invalid ' . $name . '=' . $value . '; using calculated default',
                'TelegramBotRuntimeFactory'
            );
            return null;
        }

        return (int) $parsed;
    }
}
