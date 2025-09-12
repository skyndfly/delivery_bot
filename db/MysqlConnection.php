<?php

namespace db;

use PDO;

class MysqlConnection
{
    private static ?PDO $pdo = null;

    public static function getInstance(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = new PDO('mysql:host=mysql;dbname=db', 'user', 'password');
        }
        return self::$pdo;
    }
}