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

    public function handle()
    {
        try {
            $users = $this->googleTableApi->getUserData();
            $this->userRepository->saveUsers($users);
            $unique = count(array_unique($users));
            $total = count($users);
            log_dump("Всего id: $total, уникальных: $unique");
        }catch (Throwable $e){
            log_dump("Ошибка при загрузке пользователей: " . $e->getMessage());
        }
    }

}