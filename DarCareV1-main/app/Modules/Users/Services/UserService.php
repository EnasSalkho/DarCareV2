<?php
// app/Modules/Users/Services/UserService.php

namespace App\Modules\Users\Services;

use App\Modules\Users\Contracts\UserServiceInterface;
use App\Modules\Users\Models\User;
use App\Enums\RoleEnum;


class UserService implements UserServiceInterface
{
    public function getProfile(int $userId): object
{
    $user = User::findOrFail($userId);

    $user->addresses = $user->fetchAddresses();

    return $user;
}

    public function updateProfile(int $userId, array $data): object
{
    $user = User::findOrFail($userId);
    $user->update($data);

    $user->addresses = $user->fetchAddresses();

    return $user;
}
    public function updateProfileImage(int $userId, $file): object
    {
        $user = User::findOrFail($userId);
        $path = $file->store('users/images', 'public');
        $user->update(['profile_image' => $path]);
        return $user->fresh();
    }


        //? Admin
        public function getAllUsersForAdmin(?string $search): mixed
        {
            // جلب المستخدمين يلي حسابهم "زبون" فقط وليس "فني"
            $query = User::where('role', RoleEnum::User->value);

            // إمكانية البحث باسم الزبون أو رقمه
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            return $query->orderBy('created_at', 'desc')->paginate(15);
        }

        public function deleteUserForAdmin(int $userId): bool
        {
            $user = User::findOrFail($userId);
            return $user->delete();
        }
}
