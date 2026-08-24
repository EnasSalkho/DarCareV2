<?php

use App\Modules\Ratings\Http\Controllers\Admin\AdminRatingController;
use App\Modules\Ratings\Http\Controllers\RatingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('ratings')->name('ratings.')->group(function () {
    Route::post('/',                            [RatingController::class, 'store'])->name('store');
    Route::get('/provider/{providerId}',        [RatingController::class, 'providerRatings'])->name('provider');
});

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('ratings', [AdminRatingController::class, 'index']);
    Route::delete('ratings/{id}', [AdminRatingController::class, 'destroy']);
});
