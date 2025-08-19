<?php

namespace services;

use api\GoogleTableApi;
use repositories\UserRepository;
use Throwable;

class UserSyncService
{
    private UserRepository $userRepository;
    private GoogleTableApi $googleTableApi;

    public function __construct(UserRepository $userRepository, GoogleTableApi $googleTableApi)
    {
        $this->userRepository = $userRepository;
        $this->googleTableApi = $googleTableApi;
    }

    public function handle(): void
    {
        try {
            $users = $this->googleTableApi->getUserData();
            $this->userRepository->saveUsers($users);
            $unique = count(array_unique($users));
            $total = count($users);
            log_dump("Загружено всего id: $total, уникальных: $unique", 'UserSyncService');
        }catch (Throwable $e){
            log_dump("Ошибка при загрузке пользователей: " . $e->getMessage(), 'UserSyncService');
        }
    }

}