<?php

namespace repositories;

use db\RedisConnection;
use Predis\Client;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class StepRepository
{
    private CacheInterface $cache;
    private const int DEFAULT_TTL = 3600; // 1 час по умолчанию

    public function __construct()
    {
        $this->cache = RedisConnection::getInstance();
    }

    public function getStep(int $chatId): ?string
    {
        return $this->cache->get('step_' . $chatId, function (ItemInterface $item) {
            // Если значения нет в кеше, вернет null
            $item->expiresAfter(self::DEFAULT_TTL);
            return null;
        });
    }

    public function setStep(int $chatId, string $step, ?int $ttl = null): void
    {
        $this->cache->delete('step_' . $chatId); // сначала удаляем старое значение
        $this->cache->get('step_' . $chatId, function (ItemInterface $item) use ($step, $ttl) {
            $item->expiresAfter($ttl ?? self::DEFAULT_TTL);
            return $step;
        });
    }
    public function getPath(int $path): ?string
    {
        return $this->cache->get('path_' . $path, function (ItemInterface $item) {
            $item->expiresAfter(self::DEFAULT_TTL);
            return null;
        });
    }
    public function setPath(int $chatId, string $path, ?int $ttl = null): void
    {
        $this->cache->delete('path_' . $chatId); // сначала удаляем старое значение
        $this->cache->get('path_' . $chatId, function (ItemInterface $item) use ($path, $ttl) {
            $item->expiresAfter($ttl ?? self::DEFAULT_TTL);
            return $path;
        });
    }

    public function getFirm(int $chatId): ?string
    {
        return $this->cache->get('firm_' . $chatId, function (ItemInterface $item) {
            $item->expiresAfter(self::DEFAULT_TTL);
            return null;
        });
    }

    public function setFirm(int $chatId, string $firm, ?int $ttl = null): void
    {
        $this->cache->delete('firm_' . $chatId);
        $this->cache->get('firm_' . $chatId, function (ItemInterface $item) use ($firm, $ttl) {
            $item->expiresAfter($ttl ?? self::DEFAULT_TTL);
            return $firm;
        });
    }

    public function getAddressId(int $chatId): ?int
    {
        return $this->cache->get('address_id_' . $chatId, function (ItemInterface $item) {
            $item->expiresAfter(self::DEFAULT_TTL);
            return null;
        });
    }

    public function setAddressId(int $chatId, int $addressId, ?int $ttl = null): void
    {
        $this->cache->delete('address_id_' . $chatId);
        $this->cache->get('address_id_' . $chatId, function (ItemInterface $item) use ($addressId, $ttl) {
            $item->expiresAfter($ttl ?? self::DEFAULT_TTL);
            return $addressId;
        });
    }

    public function getAddressLabel(int $chatId): ?string
    {
        return $this->cache->get('address_label_' . $chatId, function (ItemInterface $item) {
            $item->expiresAfter(self::DEFAULT_TTL);
            return null;
        });
    }

    public function setAddressLabel(int $chatId, string $label, ?int $ttl = null): void
    {
        $this->cache->delete('address_label_' . $chatId);
        $this->cache->get('address_label_' . $chatId, function (ItemInterface $item) use ($label, $ttl) {
            $item->expiresAfter($ttl ?? self::DEFAULT_TTL);
            return $label;
        });
    }
}
