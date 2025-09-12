<?php

namespace repositories;

use db\RedisConnection;
use Predis\Client;
use repositories\contracts\UserRepositoryContract;

class UserRedisRepository implements UserRepositoryContract
{
    private const string KEY = 'users:ids';
    private Client $client;

    public function __construct()
    {
        $this->client = RedisConnection::getInstance();
    }

    /**
     * @param int[] $users
     */
    public function saveUsers(array $users): void
    {
        $this->deleteAll();

        if (!empty($users)) {
            $this->client->sadd(self::KEY, ...array_map('strval', $users));
        }
    }
    public function addUser(int $userId): void
    {
        $this->client->sadd(self::KEY,[ (string)$userId]);
    }
    public function exists(int $userId): bool
    {
        return $this->client->sismember(self::KEY, (string)$userId);
    }

    public function deleteAll(): void
    {
        $this->client->del(self::KEY);
    }

}