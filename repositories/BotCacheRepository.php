<?php

namespace repositories;

use db\RedisConnection;
use Symfony\Contracts\Cache\CacheInterface;

class BotCacheRepository
{
    private const int TTL = 3600;
    private const string KEY_BOT_DATA = 'bot_data';
    private const string KEY_CUTOFF_HOUR = 'bot_cutoff_hour';

    private CacheInterface $cache;

    public function __construct()
    {
        $this->cache = RedisConnection::getInstance();
    }

    /**
     * @return array{firms: array<string,string>, address: array<string, array<int, array{id:int, address:string}>>}|null
     */
    public function getBotData(): ?array
    {
        $item = $this->cache->getItem(self::KEY_BOT_DATA);
        if (!$item->isHit()) {
            return null;
        }
        $value = $item->get();
        return is_array($value) ? $value : null;
    }

    /**
     * @param array{firms: array<string,string>, address: array<string, array<int, array{id:int, address:string}>>} $data
     */
    public function setBotData(array $data): void
    {
        $item = $this->cache->getItem(self::KEY_BOT_DATA);
        $item->expiresAfter(self::TTL);
        $item->set($data);
        $this->cache->save($item);
    }

    public function getCutoffHour(): ?int
    {
        $item = $this->cache->getItem(self::KEY_CUTOFF_HOUR);
        if (!$item->isHit()) {
            return null;
        }
        $value = $item->get();
        return is_int($value) ? $value : null;
    }

    public function setCutoffHour(int $hour): void
    {
        $item = $this->cache->getItem(self::KEY_CUTOFF_HOUR);
        $item->expiresAfter(self::TTL);
        $item->set($hour);
        $this->cache->save($item);
    }

    public function clearAll(): void
    {
        $this->cache->delete(self::KEY_BOT_DATA);
        $this->cache->delete(self::KEY_CUTOFF_HOUR);
    }
}
