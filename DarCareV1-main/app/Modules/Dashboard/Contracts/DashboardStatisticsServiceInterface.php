<?php

namespace App\Modules\Dashboard\Contracts;

interface DashboardStatisticsServiceInterface
{
    /**
     * @param  array{date_from?: string, date_to?: string}  $filters
     * @return array<string, mixed>
     */
    public function getOverview(array $filters = []): array;

    /**
     * @param  array{category_id?: int, provider_id?: int}  $filters
     * @return array<string, mixed>
     */
    public function getMonthlyRequestStatistics(int $year, array $filters = []): array;
}
