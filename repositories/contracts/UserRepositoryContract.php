<?php

namespace repositories\contracts;

interface UserRepositoryContract
{
    /**
     * @param int[] $users
     */
    public function saveUsers(array $users): void;
    public function addUser(int $userId): void;
    public function exists(int $userId): bool;

    public function deleteAll(): void;
}