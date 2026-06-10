<?php

use bootstrap\EnvLoader;
use services\TelegramBotRuntimeFactory;
use services\TelegramUpdateDispatcher;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/helpers/functions.php';

EnvLoader::load();

$running = true;
if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGTERM, static function () use (&$running): void {
        $running = false;
        log_dump('Polling stop requested by SIGTERM', 'TelegramPolling');
    });
    pcntl_signal(SIGINT, static function () use (&$running): void {
        $running = false;
        log_dump('Polling stop requested by SIGINT', 'TelegramPolling');
    });
}

$timeout = max(1, (int) ($_ENV['TELEGRAM_POLLING_TIMEOUT'] ?? 50));
$limit = max(1, min(100, (int) ($_ENV['TELEGRAM_POLLING_LIMIT'] ?? 20)));
$retrySleep = max(1, (int) ($_ENV['TELEGRAM_POLLING_RETRY_SLEEP'] ?? 5));
$allowedUpdates = json_encode(['message', 'callback_query'], JSON_THROW_ON_ERROR);
$offset = null;
$telegram = null;
$webhookDeleted = false;

log_dump('[FIX] Polling process starting with runtime refresh enabled', 'TelegramPolling');

while ($running) {
    try {
        while ($running) {
            if ($telegram === null) {
                $botToken = $_ENV['BOT_TOKEN'] ?? null;
                if ($botToken === null || $botToken === '') {
                    throw new RuntimeException('BotToken not defined');
                }
                $telegram = TelegramBotRuntimeFactory::createTelegramApi($botToken);
            }
            if (!$webhookDeleted) {
                $telegram->deleteWebhook();
                $webhookDeleted = true;
                log_dump('[FIX] Polling connected. Proxy type: ' . (TelegramBotRuntimeFactory::getTelegramProxyType() ?? 'default'), 'TelegramPolling');
            }

            $params = [
                'timeout' => $timeout,
                'limit' => $limit,
                'allowed_updates' => $allowedUpdates,
            ];
            if ($offset !== null) {
                $params['offset'] = $offset;
            }

            $updates = $telegram->getUpdates($params, false);
            foreach ($updates as $update) {
                $updateId = (int) $update->get('update_id');
                try {
                    $runtime = TelegramBotRuntimeFactory::create();
                    $dispatcher = new TelegramUpdateDispatcher($runtime);
                    $dispatcher->dispatch($update);
                    log_dump('[FIX] Update processed with refreshed runtime: ' . $updateId, 'TelegramPolling');
                } catch (Throwable $e) {
                    log_dump(
                        'Update ' . $updateId . ' failed: ' . get_class($e) . ': ' . $e->getMessage(),
                        'TelegramPolling'
                    );
                }
                $offset = $updateId + 1;
            }
        }
    } catch (Throwable $e) {
        log_dump(get_class($e) . ': ' . $e->getMessage(), 'TelegramPolling');
        $telegram = null;
        if ($running) {
            sleep($retrySleep);
            log_dump('Polling retry after sleep: ' . $retrySleep . 's', 'TelegramPolling');
        }
    }
}

log_dump('Polling process stopped', 'TelegramPolling');
