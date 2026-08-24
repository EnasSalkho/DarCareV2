<?php
// app/Modules/Categories/routes/api.php

use App\Modules\Categories\Http\Controllers\Admin\AdminCategoryController;
use App\Modules\Categories\Http\Controllers\CategoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('categories')->name('categories.')->group(function () {
    Route::get('/',      [CategoryController::class, 'index'])->name('index');
    Route::get('/{id}',  [CategoryController::class, 'show'])->name('show');
});

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::apiResource('categories', AdminCategoryController::class)->except(['show']);
});
