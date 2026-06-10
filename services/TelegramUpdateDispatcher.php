<?php

namespace services;

use DateTimeImmutable;
use DateTimeZone;
use handler\CallbackQuery;
use handler\MessageHandler;
use Telegram\Bot\Objects\Update;
use Throwable;

final class TelegramUpdateDispatcher
{
    public function __construct(private readonly TelegramBotRuntime $runtime)
    {
    }

    public function dispatch(Update $update): void
    {
        try {
            $message = $update->getMessage();
            if ($message && $message->get('from') && !$message->getFrom()->getIsBot()) {
                $handler = new MessageHandler(
                    bot: $this->runtime->bot,
                    redis: $this->runtime->redis,
                    apiDisk: $this->runtime->apiDisk,
                    authorize: $this->runtime->auth,
                    botToken: $this->runtime->botToken,
                    backApi: $this->runtime->backApi,
                    telegramProxy: $this->runtime->telegramProxy,
                    telegramProxyType: $this->runtime->telegramProxyType,
                );
                $handler->handle($update);
                return;
            }

            if ($update->get('callback_query')) {
                $currentDate = new DateTimeImmutable('now', new DateTimeZone('Europe/Moscow'));
                $this->runtime->apiDisk->createFolder($currentDate->format('d-m-Y'));
                $handler = new CallbackQuery(
                    bot: $this->runtime->bot,
                    redis: $this->runtime->redis,
                    apiDisk: $this->runtime->apiDisk,
                    currentDate: $currentDate,
                    authorize: $this->runtime->auth,
                    firms: $this->runtime->firms,
                );
                $handler->handle($update);
                return;
            }

            log_dump('Unsupported update type: ' . ($update->objectType() ?? 'unknown'), 'TelegramUpdateDispatcher');
        } catch (Throwable $e) {
            log_dump(get_class($e) . ': ' . $e->getMessage(), 'TelegramUpdateDispatcher');
            throw $e;
        }
    }
}
