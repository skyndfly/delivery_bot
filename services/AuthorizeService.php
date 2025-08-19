<?php

namespace services;

use repositories\UserRepository;

class AuthorizeService
{
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function handle(int $userId): bool
    {
        return $this->userRepository->exists($userId);
    }

}