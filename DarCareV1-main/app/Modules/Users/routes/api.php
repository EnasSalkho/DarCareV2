<?php
// app/Modules/Users/routes/api.php

use App\Modules\Users\Http\Controllers\Admin\AdminUserController;
use App\Modules\Users\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('user')->name('user.')->group(function () {
    Route::get('profile',  [UserController::class, 'profile'])->name('profile');
    Route::post('profile', [UserController::class, 'update'])->name('update');
});

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('users', [AdminUserController::class, 'index']);
    Route::delete('users/{id}', [AdminUserController::class, 'destroy']);
});
