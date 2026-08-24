<?php

namespace App\Modules\Cities\Providers;

use App\Modules\Cities\Contracts\CityServiceInterface;
use App\Modules\Cities\Services\CityService;
use Illuminate\Support\ServiceProvider;

class CitiesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CityServiceInterface::class, CityService::class);
    }
    public function boot(): void {}
}
