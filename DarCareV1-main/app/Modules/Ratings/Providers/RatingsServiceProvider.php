<?php
// app/Modules/Ratings/Providers/RatingsServiceProvider.php

namespace App\Modules\Ratings\Providers;

use App\Modules\Ratings\Contracts\RatingServiceInterface;
use App\Modules\Ratings\Services\RatingService;
use Illuminate\Support\ServiceProvider;

class RatingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RatingServiceInterface::class, RatingService::class ,

         App\Modules\Ratings\Services\RatingService::class
        );

    }
    public function boot(): void {}
}
