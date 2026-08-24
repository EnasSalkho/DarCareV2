<?php
// app/Modules/Auth/Contracts/AuthServiceInterface.php

namespace App\Modules\Auth\Contracts;

use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Auth\Http\Requests\RegisterProviderRequest;
use App\Modules\Auth\Http\Requests\RegisterUserRequest;

interface AuthServiceInterface
{
    public function registerUser(RegisterUserRequest $request): array;

    public function registerProvider(RegisterProviderRequest $request): array;

    public function login(LoginRequest $request, string $role): array;

    public function logout($user, ?string $deviceToken = null): void;
}
