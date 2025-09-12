<?php

namespace services;

use api\GoogleTableApi;
use repositories\contracts\UserRepositoryContract;
use Throwable;

class UserSyncService
{
    private UserRepositoryContract $userRepository;
    private GoogleTableApi $googleTableApi;

    public function __construct(UserRepositoryContract $userRepository, GoogleTableApi $googleTableApi)
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
            echo "Загружено всего id: $total, уникальных: $unique\n";
        }catch (Throwable $e){
            log_dump("Ошибка при загрузке пользователей: " . $e->getMessage(), 'UserSyncService');
        }
    }

}