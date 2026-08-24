<?php
// app/Modules/Notifications/Providers/NotificationsServiceProvider.php

namespace App\Modules\Notifications\Providers;

use App\Modules\Notifications\Contracts\NotificationServiceInterface;
use App\Modules\Notifications\Services\DeviceTokenService;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DeviceTokenService::class);
        $this->app->bind(NotificationServiceInterface::class, NotificationService::class);
    }

    public function boot(): void {}
}
