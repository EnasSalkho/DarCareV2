<?php

namespace App\Modules\Dashboard\Providers;

use App\Modules\Dashboard\Contracts\DashboardStatisticsServiceInterface;
use App\Modules\Dashboard\Http\Controllers\DashboardStatsController;
use App\Modules\Dashboard\Services\DashboardStatisticsService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class DashboardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            DashboardStatisticsServiceInterface::class,
            DashboardStatisticsService::class
        );
    }

    /**
     * Deprecated alias: /api/admin/dashboard/stats
     * Canonical routes live under /api/v1/admin/dashboard/* via routes/api.php.
     */
    public function boot(): void
    {
        Route::prefix('api')
            ->middleware(['api', 'auth:sanctum', 'admin'])
            ->get('admin/dashboard/stats', [DashboardStatsController::class, 'getSummaryStats']);
    }
}
