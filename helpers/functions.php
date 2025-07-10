<?php
function log_dump($var, $title = '')
{
    ob_start();
    echo "\n--- $title ---\n";
    var_dump($var);
    echo "\n";
    file_put_contents("log.txt", ob_get_clean(), FILE_APPEND);
}