<?php

namespace services;

use api\BackApi;
use api\TelegramBotApi;
use api\YandexDiskApi;
use repositories\StepRepository;
use Telegram\Bot\Api;

final class TelegramBotRuntime
{
    public function __construct(
        public readonly Api $telegram,
        public readonly TelegramBotApi $bot,
        public readonly StepRepository $redis,
        public readonly YandexDiskApi $apiDisk,
        public readonly AuthorizeService $auth,
        public readonly BackApi $backApi,
        public readonly string $botToken,
        public readonly string $telegramProxy,
        public readonly ?string $telegramProxyType,
        public readonly array $firms,
    ) {
    }
}
