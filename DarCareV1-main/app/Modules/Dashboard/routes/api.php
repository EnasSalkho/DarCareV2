<?php

use App\Modules\Dashboard\Http\Controllers\DashboardStatsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('dashboard/stats', [DashboardStatsController::class, 'getSummaryStats']);
    Route::get('dashboard/overview', [DashboardStatsController::class, 'overview']);
    Route::get('dashboard/request-statistics/monthly', [DashboardStatsController::class, 'monthlyRequestStatistics']);
});
