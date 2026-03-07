<?php

namespace repositories\contracts;

interface UserRepositoryContract
{
    /**
     * @param array<int, array{id:int, username:string|null, phone:string|null, name:string|null}> $users
     */
    public function saveUsers(array $users): void;
    public function addUser(int $userId): void;
    public function exists(int $userId): bool;

    public function deleteAll(): void;
}
