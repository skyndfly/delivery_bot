<?php

namespace services;

use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use api\BackApi;
use repositories\BotCacheRepository;
use repositories\contracts\UserRepositoryContract;

class AuthorizeService
{
    private UserRepositoryContract $userRepository;
    private array $whiteList = [
        1535637656,
        595913846
    ];

    public function __construct(UserRepositoryContract $userRepository, private BackApi $backApi, private BotCacheRepository $botCacheRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function handle(int $userId, $chatId): bool
    {
        if (in_array($chatId, $this->whiteList)) {
            return true;
        }
        if (!$this->isServiceAvailable()) {
            $hour = $this->getCutoffHour();
            $hourLabel = str_pad((string) $hour, 2, '0', STR_PAD_LEFT);
            throw new DomainException("Сервис недоступен в период с {$hourLabel}:00 до 23:59");
        }
        return $this->userRepository->exists($userId);
    }

    private function isServiceAvailable(): bool
    {
        $currentTime = new DateTimeImmutable('now', new DateTimeZone('Europe/Moscow'));
        $currentHour = (int) $currentTime->format('H');
        $currentMinute = (int) $currentTime->format('i');

        $cutoffHour = $this->getCutoffHour();
        $currentTimeMinutes = $currentHour * 60 + $currentMinute;
        $cutoffMinutes = $cutoffHour * 60;
        return $currentTimeMinutes < $cutoffMinutes || $currentTimeMinutes > 23 * 60 + 59;
    }

    private function getCutoffHour(): int
    {
        try {
            $cached = $this->botCacheRepository->getCutoffHour();
            if ($cached !== null) {
                return $cached;
            }
            $settings = $this->backApi->getBotSettings();
            $hour = (int) ($settings['cutoffHour'] ?? 16);
            if ($hour < 0 || $hour > 23) {
                return 16;
            }
            $this->botCacheRepository->setCutoffHour($hour);
            return $hour;
        } catch (\Throwable) {
            return 16;
        }
    }
}
