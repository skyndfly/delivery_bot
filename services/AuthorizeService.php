<?php

namespace services;

use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use repositories\contracts\UserRepositoryContract;

class AuthorizeService
{
    private UserRepositoryContract $userRepository;
    private array $whiteList = [
        1535637656,
        595913846
    ];

    public function __construct(UserRepositoryContract $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function handle(int $userId, $chatId): bool
    {
        if (in_array($chatId, $this->whiteList)) {
            return true;
        }
        if (!$this->isServiceAvailable()) {
            throw new DomainException('Сервис недоступен в период с 16:00 до 23:59');
                }
        return $this->userRepository->exists($userId);
    }

    private function isServiceAvailable(): bool
    {
        $currentTime = new DateTimeImmutable('now', new DateTimeZone('Europe/Moscow'));
        $currentHour = (int) $currentTime->format('H');
        $currentMinute = (int) $currentTime->format('i');

        // Текущее время в минутах от начала дня
        $currentTimeMinutes = $currentHour * 60 + $currentMinute;
        // Проверяем период с 16:00 (960 минут) до 23:59 (1439 минут)
        return $currentTimeMinutes < 16 * 60 || $currentTimeMinutes > 23 * 60 + 59;
    }

}