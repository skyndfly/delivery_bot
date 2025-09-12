<?php

namespace services;

use repositories\contracts\UserRepositoryContract;
use repositories\UserRedisRepository;

class AuthorizeService
{
    private UserRepositoryContract $userRepository;

    public function __construct(UserRepositoryContract $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function handle(int $userId): bool
    {
        return $this->userRepository->exists($userId);
    }

}