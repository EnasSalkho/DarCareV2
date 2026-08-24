<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Enums\RoleEnum;
use App\Enums\ProviderStatusEnum;
use App\Enums\RequestStatusEnum;
use App\Modules\Dashboard\Contracts\DashboardStatisticsServiceInterface;
use App\Modules\Dashboard\Http\Requests\MonthlyRequestStatisticsRequest;
use App\Modules\Providers\Models\Provider;
use App\Modules\ServiceRequests\Models\ServiceRequest;
use App\Modules\Users\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Throwable;

class DashboardStatsController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly DashboardStatisticsServiceInterface $dashboardStatisticsService
    ) {}

    /**
     * Quick summary counters for the admin dashboard.
     */
    public function getSummaryStats(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'total_customers' => User::where('role', RoleEnum::User->value)->count(),
                'total_providers' => Provider::count(),
                'active_now_providers' => Provider::where('status', ProviderStatusEnum::Available->value)->count(),
                'total_requests' => ServiceRequest::count(),
                'pending_requests' => ServiceRequest::where('status', RequestStatusEnum::Pending->value)->count(),
                'completed_requests' => ServiceRequest::where('status', RequestStatusEnum::Completed->value)->count(),
                'urgent_pending_requests' => ServiceRequest::where('urgency', 'urgent')
                    ->where('status', RequestStatusEnum::Pending->value)
                    ->count(),
            ],
        ], 200);
    }

    public function overview(): JsonResponse
    {
        try {
            $data = $this->dashboardStatisticsService->getOverview();

            return $this->success($data, 'Dashboard overview retrieved successfully.');
        } catch (Throwable $e) {
            report($e);

            return $this->error('Unable to retrieve dashboard overview.', null, 500);
        }
    }

    public function monthlyRequestStatistics(MonthlyRequestStatisticsRequest $request): JsonResponse
    {
        try {
            $data = $this->dashboardStatisticsService->getMonthlyRequestStatistics(
                $request->year(),
                $request->filters()
            );

            return $this->success($data, 'Monthly request statistics retrieved successfully.');
        } catch (Throwable $e) {
            report($e);

            return $this->error('Unable to retrieve monthly request statistics.', null, 500);
        }
    }
}
