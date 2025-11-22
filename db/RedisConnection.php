<?php

namespace db;

use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Contracts\Cache\CacheInterface;

class RedisConnection
{
    private static ?CacheInterface $redis = null;

    public static function getInstance(): CacheInterface
    {
        if (self::$redis === null) {
            $redisConnection = RedisAdapter::createConnection(
                'redis://redis:6379'
            );
            self::$redis = new RedisAdapter(
                $redisConnection,
                $namespace = '',
                $defaultLifetime = 0
            );

        }
        return self::$redis;
    }
}