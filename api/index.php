<?php

use api\WebhookController;
use repositories\UserMysqlRepository;

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ .'/../helpers/functions.php';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (isset($_GET['action'])) {
    $userRepository = new UserMysqlRepository();
    $controller = new WebhookController(
        userRepository: $userRepository
    );
    $action = $_GET['action'];
    if ($action === 'webhook-add-user') {
        $controller->addUser();
        exit;
    }
}


http_response_code(404);
echo "Not found";