<?php
// app/Modules/Providers/Providers/ProvidersServiceProvider.php

namespace App\Modules\Providers\Providers;

use App\Modules\Providers\Contracts\ProviderServiceInterface;
use App\Modules\Providers\Services\ProviderService;
use Illuminate\Support\ServiceProvider;

class ProvidersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProviderServiceInterface::class, ProviderService::class);
    }
    public function boot(): void {}
}
