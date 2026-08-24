<?php
// app/Modules/Locations/Providers/LocationsServiceProvider.php

namespace App\Modules\Locations\Providers;

use App\Modules\Locations\Contracts\LocationServiceInterface;
use App\Modules\Locations\Services\LocationService;
use Illuminate\Support\ServiceProvider;

class LocationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LocationServiceInterface::class, LocationService::class);
    }
    public function boot(): void {}
}
