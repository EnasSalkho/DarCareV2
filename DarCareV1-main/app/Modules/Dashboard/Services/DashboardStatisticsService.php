<?php

namespace App\Modules\Dashboard\Services;

use App\Enums\ProviderStatusEnum;
use App\Enums\RequestStatusEnum;
use App\Modules\Categories\Models\Category;
use App\Modules\Dashboard\Contracts\DashboardStatisticsServiceInterface;
use App\Modules\Providers\Models\Provider;
use App\Modules\Ratings\Models\Rating;
use App\Modules\ServiceRequests\Models\ServiceRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardStatisticsService implements DashboardStatisticsServiceInterface
{
    /**
     * Month labels (calendar names, not domain data).
     *
     * @var array<int, array{key: string, en: string, ar: string}>
     */
    private const MONTHS = [
        1 => ['key' => 'january', 'en' => 'January', 'ar' => 'يناير'],
        2 => ['key' => 'february', 'en' => 'February', 'ar' => 'فبراير'],
        3 => ['key' => 'march', 'en' => 'March', 'ar' => 'مارس'],
        4 => ['key' => 'april', 'en' => 'April', 'ar' => 'نيسان'],
        5 => ['key' => 'may', 'en' => 'May', 'ar' => 'مايو'],
        6 => ['key' => 'june', 'en' => 'June', 'ar' => 'يونيو'],
        7 => ['key' => 'july', 'en' => 'July', 'ar' => 'يوليو'],
        8 => ['key' => 'august', 'en' => 'August', 'ar' => 'أغسطس'],
        9 => ['key' => 'september', 'en' => 'September', 'ar' => 'سبتمبر'],
        10 => ['key' => 'october', 'en' => 'October', 'ar' => 'أكتوبر'],
        11 => ['key' => 'november', 'en' => 'November', 'ar' => 'نوفمبر'],
        12 => ['key' => 'december', 'en' => 'December', 'ar' => 'ديسمبر'],
    ];

    public function getOverview(array $filters = []): array
    {
        // Overview date filters are intentionally not applied: latest lists stay
        // globally latest while category/top-artisan date scoping would make the
        // payload inconsistent. Use monthly statistics for time-bounded reporting.
        unset($filters);

        return [
            'latest_requests' => $this->latestRequests(),
            'latest_ratings' => $this->latestRatings(),
            'top_artisans' => $this->topArtisans(),
            'category_distribution' => $this->categoryDistribution(),
        ];
    }

    public function getMonthlyRequestStatistics(int $year, array $filters = []): array
    {
        $statuses = array_map(
            static fn (RequestStatusEnum $status) => $status->value,
            RequestStatusEnum::cases()
        );

        $start = Carbon::create($year, 1, 1, 0, 0, 0, config('app.timezone'))->startOfDay();
        $endExclusive = $start->copy()->addYear();

        $query = ServiceRequest::query()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $endExclusive);

        if (! empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        if (! empty($filters['provider_id'])) {
            $query->where('provider_id', (int) $filters['provider_id']);
        }

        $monthExpression = $this->monthExpression('created_at');

        $rows = $query
            ->selectRaw("{$monthExpression} as month_number")
            ->selectRaw('status')
            ->selectRaw('COUNT(*) as aggregate_count')
            ->groupByRaw("{$monthExpression}, status")
            ->get();

        /** @var array<int, array<string, int>> $countsByMonth */
        $countsByMonth = [];
        foreach (range(1, 12) as $month) {
            $countsByMonth[$month] = array_fill_keys($statuses, 0);
        }

        foreach ($rows as $row) {
            $month = (int) $row->month_number;
            $status = (string) $row->status;

            if (! isset($countsByMonth[$month]) || ! array_key_exists($status, $countsByMonth[$month])) {
                continue;
            }

            $countsByMonth[$month][$status] = (int) $row->aggregate_count;
        }

        $yearTotals = array_fill_keys($statuses, 0);
        $annualTotal = 0;
        $months = [];

        foreach (range(1, 12) as $month) {
            $statusCounts = $countsByMonth[$month];
            $monthTotal = array_sum($statusCounts);
            $annualTotal += $monthTotal;

            foreach ($statuses as $status) {
                $yearTotals[$status] += $statusCounts[$status];
            }

            $statusPayload = [];
            foreach ($statuses as $status) {
                $count = $statusCounts[$status];
                $statusPayload[$status] = [
                    'count' => $count,
                    'percentage' => $this->percentage($count, $monthTotal),
                ];
            }

            $meta = self::MONTHS[$month];

            $months[] = [
                'month' => $month,
                'month_key' => $meta['key'],
                'month_name' => $meta['en'],
                'month_name_ar' => $meta['ar'],
                'total' => $monthTotal,
                // Placeholder; filled after annualTotal is known.
                'annual_percentage' => 0.0,
                'statuses' => $statusPayload,
            ];
        }

        foreach ($months as $index => $monthRow) {
            $months[$index]['annual_percentage'] = $this->percentage(
                $monthRow['total'],
                $annualTotal
            );
        }

        return [
            'year' => $year,
            'annual_total' => $annualTotal,
            'months' => $months,
            'year_totals' => array_merge(['total' => $annualTotal], $yearTotals),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function latestRequests(): array
    {
        $requests = ServiceRequest::query()
            ->with([
                'user:id,name',
                'provider:id,name',
                'category:id,name',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return $requests->map(function (ServiceRequest $request) {
            return [
                'id' => $request->id,
                'customer' => $request->user
                    ? ['id' => $request->user->id, 'name' => $request->user->name]
                    : null,
                'artisan' => $request->provider
                    ? ['id' => $request->provider->id, 'name' => $request->provider->name]
                    : null,
                'category' => $request->category
                    ? [
                        'id' => $request->category->id,
                        'name' => $request->category->name,
                    ]
                    : null,
                'status' => $request->status,
                'urgency' => $request->urgency,
                'scheduled_at' => $this->toIso8601($request->scheduled_at),
                'created_at' => $this->toIso8601($request->created_at),
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function latestRatings(): array
    {
        $ratings = Rating::query()
            ->with([
                'user:id,name',
                'provider:id,name',
                'serviceRequest:id',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return $ratings->map(function (Rating $rating) {
            return [
                'id' => $rating->id,
                'rating' => (int) $rating->rating,
                'comment' => $rating->comment,
                'customer' => $rating->user
                    ? ['id' => $rating->user->id, 'name' => $rating->user->name]
                    : null,
                'artisan' => $rating->provider
                    ? ['id' => $rating->provider->id, 'name' => $rating->provider->name]
                    : null,
                'service_request' => $rating->serviceRequest
                    ? ['id' => $rating->serviceRequest->id]
                    : ['id' => $rating->service_request_id],
                'created_at' => $this->toIso8601($rating->created_at),
            ];
        })->all();
    }

    /**
     * Top artisans ranking (deterministic):
     * 1) AVG(ratings.rating) DESC (0 when no ratings)
     * 2) COUNT(ratings.id) DESC
     * 3) COUNT(completed service_requests) DESC
     * 4) providers.id ASC
     *
     * Uses live aggregates rather than stored rating_avg so ranking stays
     * consistent with ratings_count. Excludes soft-deleted and suspended providers.
     *
     * @return list<array<string, mixed>>
     */
    private function topArtisans(): array
    {
        $completed = RequestStatusEnum::Completed->value;

        $providers = Provider::query()
            ->where('status', '!=', ProviderStatusEnum::Suspended->value)
            ->with(['categories:id,name'])
            ->withAvg('ratings', 'rating')
            ->withCount('ratings')
            ->withCount([
                'serviceRequests as completed_requests_count' => function ($query) use ($completed) {
                    $query->where('status', $completed);
                },
            ])
            ->orderByRaw('COALESCE(ratings_avg_rating, 0) DESC')
            ->orderByDesc('ratings_count')
            ->orderByDesc('completed_requests_count')
            ->orderBy('id')
            ->limit(5)
            ->get();

        return $providers->map(function (Provider $provider) {
            $average = $provider->ratings_avg_rating !== null
                ? round((float) $provider->ratings_avg_rating, 2)
                : 0.0;

            return [
                'id' => $provider->id,
                'name' => $provider->name,
                'profile_image' => $provider->profile_image
                    ? asset('storage/'.$provider->profile_image)
                    : null,
                'average_rating' => $average,
                'ratings_count' => (int) $provider->ratings_count,
                'completed_requests_count' => (int) $provider->completed_requests_count,
                'categories' => $provider->categories
                    ->map(fn (Category $category) => [
                        'id' => $category->id,
                        'name' => $category->name,
                    ])
                    ->values()
                    ->all(),
            ];
        })->all();
    }

    /**
     * Distribution across every active category.
     * Denominator = total non-deleted service requests that have a category_id
     * (category_id is required by schema, so this is all non-deleted requests).
     * Inactive categories are excluded; active categories with zero requests are included.
     *
     * @return array{total_requests: int, categories: list<array<string, mixed>>}
     */
    private function categoryDistribution(): array
    {
        $counts = ServiceRequest::query()
            ->select('category_id')
            ->selectRaw('COUNT(*) as requests_count')
            ->groupBy('category_id')
            ->pluck('requests_count', 'category_id');

        $totalRequests = (int) $counts->sum();

        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'name']);

        $payload = $categories->map(function (Category $category) use ($counts, $totalRequests) {
            $requestCount = (int) ($counts[$category->id] ?? 0);

            return [
                'category_id' => $category->id,
                'name' => $category->name,
                'requests_count' => $requestCount,
                'percentage' => $this->percentage($requestCount, $totalRequests),
            ];
        })->all();

        return [
            'total_requests' => $totalRequests,
            'categories' => $payload,
        ];
    }

    private function percentage(int|float $part, int|float $whole): float
    {
        if ($whole <= 0) {
            return 0.0;
        }

        return round(($part / $whole) * 100, 2);
    }

    private function toIso8601(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->toIso8601String();
        }

        return Carbon::parse($value)->toIso8601String();
    }

    private function monthExpression(string $column): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => "CAST(strftime('%m', {$column}) AS INTEGER)",
            'pgsql' => "EXTRACT(MONTH FROM {$column})::INTEGER",
            default => "MONTH({$column})",
        };
    }
}
