<?php

namespace repositories;

use db\MysqlConnection;
use PDO;
use repositories\contracts\UserRepositoryContract;

class UserMysqlRepository implements UserRepositoryContract
{
    private PDO $connection;
    private const TABLE = 'users';

    public function __construct()
    {
        $this->connection = MysqlConnection::getInstance();
        $this->ensureTableExists();
    }

    private function ensureTableExists(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS " . self::TABLE . " (
                id BIGINT PRIMARY KEY
            )
        ";
        $this->connection->exec($sql);
    }

    /**
     * @param int[] $users
     */
    public function saveUsers(array $users): void
    {
        $this->deleteAll();
        if (!empty($users)) {
            $placeholders = implode(',', array_fill(0, count($users), '(?)'));
            $stmt = $this->connection->prepare("INSERT IGNORE INTO " . self::TABLE . " (id) VALUES $placeholders");
            foreach ($users as $index => $userId) {
                $stmt->bindValue($index + 1, $userId, PDO::PARAM_INT);
            }
            $stmt->execute();
        }
    }

    public function addUser(int $userId): void
    {
        $stmt = $this->connection->prepare("INSERT IGNORE INTO " . self::TABLE . " (id) VALUES (:id)");
        $stmt->execute(['id' => $userId]);
    }

    public function exists(int $userId): bool
    {
        $stmt = $this->connection->prepare("SELECT 1 FROM " . self::TABLE . " WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $userId]);
        return (bool)$stmt->fetchColumn();
    }

    public function deleteAll(): void
    {
        $this->connection->exec("TRUNCATE TABLE " . self::TABLE);
    }
}
