<?php
// app/Modules/Auth/Providers/AuthServiceProvider.php

namespace App\Modules\Auth\Providers;

use App\Modules\Auth\Contracts\AuthServiceInterface;
use App\Modules\Auth\Services\AuthService;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AuthServiceInterface::class, AuthService::class);
    }

    public function boot(): void {}
}
