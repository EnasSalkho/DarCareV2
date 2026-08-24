<?php
// app/Modules/Categories/Providers/CategoriesServiceProvider.php

namespace App\Modules\Categories\Providers;

use App\Modules\Categories\Contracts\CategoryServiceInterface;
use App\Modules\Categories\Services\CategoryService;
use Illuminate\Support\ServiceProvider;

class CategoriesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CategoryServiceInterface::class, CategoryService::class);
    }
    public function boot(): void {}
}
