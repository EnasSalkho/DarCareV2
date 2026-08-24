<?php
// app/Modules/Favorites/Providers/FavoritesServiceProvider.php

namespace App\Modules\Favorites\Providers;

// use App\Modules\Favorites\Contracts\FavoriteServiceInterface;
// use App\Modules\Favorites\Services\FavoriteService;
use Illuminate\Support\ServiceProvider;

class FavoritesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
       $this->app->bind(
    \App\Modules\Favorites\Contracts\FavoriteServiceInterface::class,
    \App\Modules\Favorites\Services\FavoriteService::class
    );
    }
    public function boot(): void {}
}
