<?php
// app/Modules/Favorites/routes/api.php

use App\Modules\Favorites\Http\Controllers\Admin\AdminFavoriteController;
use App\Modules\Favorites\Http\Controllers\FavoriteController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('favorites')->name('favorites.')->group(function () {
    Route::get('/',                        [FavoriteController::class, 'index'])->name('index');
    Route::post('/{providerId}/toggle',    [FavoriteController::class, 'toggle'])->name('toggle');
});

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('favorites/top-providers', [AdminFavoriteController::class, 'getTopFavorited']);
});
