<?php

use bootstrap\EnvLoader;
use services\TelegramBotRuntimeFactory;

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../helpers/functions.php';

EnvLoader::load();

$config = TelegramBotRuntimeFactory::getTelegramHttpClientConfig();

$message = 'Telegram timeout check: polling_timeout=' . $config['polling_timeout']
    . ', http_timeout=' . $config['timeout']
    . ', connect_timeout=' . $config['connect_timeout'];

if ($config['timeout'] <= $config['polling_timeout']) {
    $error = '[ERROR] ' . $message . ' | TELEGRAM_HTTP_TIMEOUT must be greater than TELEGRAM_POLLING_TIMEOUT';
    log_dump($error, 'TelegramTimeoutCheck');
    fwrite(STDERR, $error . PHP_EOL);
    exit(1);
}

if ($config['timeout_adjusted']) {
    $error = '[ERROR] ' . $message
        . ' | configured TELEGRAM_HTTP_TIMEOUT=' . $config['configured_timeout']
        . ' is not greater than TELEGRAM_POLLING_TIMEOUT; runtime adjusted it, but env must be fixed';
    log_dump($error, 'TelegramTimeoutCheck');
    fwrite(STDERR, $error . PHP_EOL);
    exit(1);
}

log_dump('[INFO] ' . $message . ' | ok', 'TelegramTimeoutCheck');
echo $message . ' | ok' . PHP_EOL;
