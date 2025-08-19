<?php
function log_dump($var, $title = '')
{
    // Создаем папку logs если ее нет
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    ob_start();
    echo date("Y-m-d H:i:s");
    echo "\n--- $title ---\n";
    var_dump($var);
    echo "\n";
    file_put_contents($logDir . "/log.txt", ob_get_clean(), FILE_APPEND);
}