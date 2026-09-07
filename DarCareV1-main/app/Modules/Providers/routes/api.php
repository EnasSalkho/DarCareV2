<?php
// app/Modules/Providers/routes/api.php

use App\Modules\Providers\Http\Controllers\Admin\AdminProviderController;
use App\Modules\Providers\Http\Controllers\ProviderController;
use Illuminate\Support\Facades\Route;

// Public
Route::prefix('providers')->name('providers.')->group(function () {
    Route::get('/search',           [ProviderController::class, 'search'])->name('search');
    Route::get('/nearby',           [ProviderController::class, 'nearby'])->name('nearby');
    Route::get('/{id}',             [ProviderController::class, 'show'])->name('show');
    Route::get('/',                 [ProviderController::class,'index']);
});

// Provider Auth
Route::middleware('auth:sanctum')->prefix('provider')->name('provider.')->group(function () {
    Route::get('profile',           [ProviderController::class, 'profile'])->name('profile');
    Route::post('profile',          [ProviderController::class, 'update'])->name('update');
    Route::patch('toggle-status',   [ProviderController::class, 'toggleStatus'])->name('toggleStatus');
});

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('providers', [AdminProviderController::class, 'index']);
    Route::patch('providers/{id}/status', [AdminProviderController::class, 'updateStatus']);
    Route::delete('providers/{id}', [AdminProviderController::class, 'destroy']);
    Route::patch(
    'providers/{id}/verification-status',
    [AdminProviderController::class, 'updateVerificationStatus']
);
});
Route::get(
    'providers/category/{categoryId}',
    [ProviderController::class, 'byCategory']
);
