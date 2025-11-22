<?php

namespace db;

use PDO;

class PostgresConnection
{
    private static ?PDO $pdo = null;

    public static function getInstance(): PDO
    {
        if (self::$pdo === null) {
            // Используем имя сервиса 'postgres' из второго проекта
            // и порт 5432
            self::$pdo = new PDO(
                'pgsql:host=postgres;port=5432;dbname=hummingbird',
                'user',
                'password'
            );
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        return self::$pdo;
    }
}