<?php

use api\GoogleTableApi;
use bootstrap\EnvLoader;
use repositories\UserRepository;
use services\UserSyncService;

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ .'/../helpers/functions.php';

EnvLoader::load();

$table = new GoogleTableApi($_ENV['TABLE_URL']);
$userRepository = new UserRepository();

$service = new UserSyncService(
    userRepository: $userRepository,
    googleTableApi: $table,
);

$service->handle();