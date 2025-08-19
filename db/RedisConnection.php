<?php

namespace db;

use Predis\Client;

class RedisConnection
{
    private static ?Client $redis = null;

    public static function getInstance(): Client
    {
        if (self::$redis === null) {
            self::$redis = new Client([
                'scheme' => 'tcp',
                'host' => 'redis',
                'port' => 6379,
            ]);

        }
        return self::$redis;
    }
}