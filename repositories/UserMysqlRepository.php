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
                id BIGINT PRIMARY KEY,
                username VARCHAR(255) NULL,
                phone VARCHAR(50) NULL,
                name VARCHAR(255) NULL,
                updated_at DATETIME NULL
            )
        ";
        $this->connection->exec($sql);
        $this->ensureColumnExists('username', 'VARCHAR(255) NULL');
        $this->ensureColumnExists('phone', 'VARCHAR(50) NULL');
        $this->ensureColumnExists('name', 'VARCHAR(255) NULL');
        $this->ensureColumnExists('updated_at', 'DATETIME NULL');
    }

    private function ensureColumnExists(string $column, string $definition): void
    {
        $stmt = $this->connection->prepare("SHOW COLUMNS FROM " . self::TABLE . " LIKE :column");
        $stmt->execute(['column' => $column]);
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($exists === false) {
            $this->connection->exec("ALTER TABLE " . self::TABLE . " ADD COLUMN {$column} {$definition}");
        }
    }

    /**
     * @param array<int, array{id:int, username:string|null, phone:string|null, name:string|null}> $users
     */
    public function saveUsers(array $users): void
    {
        $this->deleteAll();
        if (!empty($users)) {
            $placeholders = implode(',', array_fill(0, count($users), '(?, ?, ?, ?, ?)'));
            $stmt = $this->connection->prepare(
                "INSERT IGNORE INTO " . self::TABLE . " (id, username, phone, name, updated_at) VALUES {$placeholders}"
            );
            $i = 1;
            foreach ($users as $user) {
                $stmt->bindValue($i++, (int) $user['id'], PDO::PARAM_INT);
                $stmt->bindValue($i++, $user['username']);
                $stmt->bindValue($i++, $user['phone']);
                $stmt->bindValue($i++, $user['name']);
                $stmt->bindValue($i++, date('Y-m-d H:i:s'));
            }
            $stmt->execute();
        }
    }

    public function addUser(int $userId): void
    {
        $stmt = $this->connection->prepare(
            "INSERT IGNORE INTO " . self::TABLE . " (id, updated_at) VALUES (:id, :updated_at)"
        );
        $stmt->execute(['id' => $userId, 'updated_at' => date('Y-m-d H:i:s')]);
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

    /**
     * @return array<int, array{id:int, username:string|null, phone:string|null, name:string|null}>
     */
    public function searchUsers(?string $phone, ?string $chatId, int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT id, username, phone, name FROM " . self::TABLE . " WHERE 1=1";
        $params = [];
        if ($chatId !== null && $chatId !== '') {
            $sql .= " AND id = :id";
            $params['id'] = (int) $chatId;
        }
        if ($phone !== null && $phone !== '') {
            $phoneDigits = preg_replace('/\\D+/', '', $phone);
            if ($phoneDigits !== '') {
                $sql .= " AND " . $this->phoneDigitsSql() . " LIKE :phone_digits";
                $params['phone_digits'] = '%' . $phoneDigits . '%';
            }
        }
        $limit = max(1, (int) $limit);
        $offset = max(0, (int) $offset);
        $sql .= " ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'username' => $row['username'] ?? null,
                'phone' => $row['phone'] ?? null,
                'name' => $row['name'] ?? null,
            ];
        }, $rows ?: []);
    }

    public function countUsers(?string $phone, ?string $chatId): int
    {
        $sql = "SELECT COUNT(*) FROM " . self::TABLE . " WHERE 1=1";
        $params = [];
        if ($chatId !== null && $chatId !== '') {
            $sql .= " AND id = :id";
            $params['id'] = (int) $chatId;
        }
        if ($phone !== null && $phone !== '') {
            $phoneDigits = preg_replace('/\\D+/', '', $phone);
            if ($phoneDigits !== '') {
                $sql .= " AND " . $this->phoneDigitsSql() . " LIKE :phone_digits";
                $params['phone_digits'] = '%' . $phoneDigits . '%';
            }
        }
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function phoneDigitsSql(): string
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, '+', ''), '-', ''), '(', ''), ')', ''), ' ', '')";
    }
}
