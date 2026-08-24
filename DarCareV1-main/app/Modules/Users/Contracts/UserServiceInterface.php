<?php
// app/Modules/Users/Contracts/UserServiceInterface.php

namespace App\Modules\Users\Contracts;

interface UserServiceInterface
{
    public function getProfile(int $userId): object;
    public function updateProfile(int $userId, array $data): object;
    public function updateProfileImage(int $userId, $file): object;

        //? Admin
    public function getAllUsersForAdmin(?string $search): mixed;
    public function deleteUserForAdmin(int $userId): bool;
}
