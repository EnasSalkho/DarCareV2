<?php
// app/Modules/Users/Providers/UsersServiceProvider.php

namespace App\Modules\Users\Providers;

use App\Modules\Users\Contracts\UserServiceInterface;
use App\Modules\Users\Services\UserService;
use Illuminate\Support\ServiceProvider;

class UsersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserServiceInterface::class, UserService::class);
    }
    public function boot(): void {}
}
