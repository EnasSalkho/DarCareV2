<?php
// app/Modules/Auth/routes/api.php

use App\Modules\Auth\Http\Controllers\AuthController;
use App\Modules\Auth\Http\Controllers\AdminAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('register/user',     [AuthController::class, 'registerUser'])->name('register.user');
    Route::post('register/provider', [AuthController::class, 'registerProvider'])->name('register.provider');
    Route::post('login/user',        [AuthController::class, 'loginUser'])->name('login.user');
    Route::post('login/provider',    [AuthController::class, 'loginProvider'])->name('login.provider');
    Route::post('admin/login', [AdminAuthController::class, 'adminLogin']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    });
});
