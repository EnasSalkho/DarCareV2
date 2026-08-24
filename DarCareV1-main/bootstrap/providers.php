<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    App\Modules\Dashboard\Providers\DashboardServiceProvider::class,
    App\Modules\Ratings\Providers\RatingsServiceProvider::class,
    App\Modules\Favorites\Providers\FavoritesServiceProvider::class

];
