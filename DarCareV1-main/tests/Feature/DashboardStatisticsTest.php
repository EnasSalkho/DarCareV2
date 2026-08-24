<?php

use App\Enums\RequestStatusEnum;
use App\Modules\Categories\Models\Category;
use App\Modules\Providers\Models\Provider;
use App\Modules\Ratings\Models\Rating;
use App\Modules\ServiceRequests\Models\ServiceRequest;
use App\Modules\Users\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

function seedDashboardCategory(string $name): Category
{
    return Category::query()->create([
        'name' => $name,
        'slug' => \Illuminate\Support\Str::slug($name).'-'.fake()->unique()->numerify('###'),
        'is_active' => true,
    ]);
}

function insertServiceRequests(
    User $customer,
    Provider $provider,
    Category $category,
    string $status,
    Carbon $createdAt,
    int $count
): void {
    $rows = [];
    $now = now();

    for ($i = 0; $i < $count; $i++) {
        $rows[] = [
            'user_id' => $customer->id,
            'provider_id' => $provider->id,
            'category_id' => $category->id,
            'address_id' => null,
            'description' => "Dashboard stats request {$status} {$i}",
            'urgency' => 'normal',
            'image' => null,
            'status' => $status,
            'temp_latitude' => null,
            'temp_longitude' => null,
            'temp_label' => null,
            'scheduled_at' => null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'deleted_at' => null,
        ];
    }

    foreach (array_chunk($rows, 100) as $chunk) {
        DB::table('service_requests')->insert($chunk);
    }
}

it('rejects unauthenticated dashboard overview access', function () {
    $this->getJson('/api/v1/admin/dashboard/overview')->assertUnauthorized();
});

it('rejects customers and providers from dashboard overview', function () {
    Sanctum::actingAs(createCustomer());
    $this->getJson('/api/v1/admin/dashboard/overview')->assertForbidden();

    Sanctum::actingAs(createProvider());
    $this->getJson('/api/v1/admin/dashboard/overview')->assertForbidden();
});

it('allows admin to retrieve dashboard overview', function () {
    Sanctum::actingAs(createAdmin());

    $this->getJson('/api/v1/admin/dashboard/overview')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'latest_requests',
                'latest_ratings',
                'top_artisans',
                'category_distribution' => [
                    'total_requests',
                    'categories',
                ],
            ],
            'errors',
        ]);
});

it('returns at most five latest requests ordered newest first', function () {
    $admin = createAdmin();
    $customer = createCustomer();
    $provider = createProvider();
    $category = seedDashboardCategory('Electrical');

    foreach (range(1, 7) as $i) {
        ServiceRequest::query()->create([
            'user_id' => $customer->id,
            'provider_id' => $provider->id,
            'category_id' => $category->id,
            'description' => "Request {$i}",
            'urgency' => 'normal',
            'status' => 'pending',
            'created_at' => now()->subMinutes(10 - $i),
            'updated_at' => now()->subMinutes(10 - $i),
        ]);
    }

    Sanctum::actingAs($admin);
    $response = $this->getJson('/api/v1/admin/dashboard/overview')->assertOk();

    $latest = $response->json('data.latest_requests');
    expect($latest)->toHaveCount(5);
    expect($latest[0]['id'])->toBeGreaterThan($latest[1]['id']);
    expect($latest[0])->not->toHaveKey('password');
    expect($latest[0]['customer'])->toHaveKeys(['id', 'name']);
    expect($latest[0]['artisan'])->toHaveKeys(['id', 'name']);
    expect($latest[0])->not->toHaveKey('temp_latitude');
});

it('returns at most five latest ratings ordered newest first', function () {
    $admin = createAdmin();
    $customer = createCustomer();
    $provider = createProvider();
    $category = seedDashboardCategory('Plumbing');

    foreach (range(1, 6) as $i) {
        $request = ServiceRequest::query()->create([
            'user_id' => $customer->id,
            'provider_id' => $provider->id,
            'category_id' => $category->id,
            'description' => "Rated request {$i}",
            'urgency' => 'normal',
            'status' => 'completed',
        ]);

        Rating::query()->create([
            'user_id' => $customer->id,
            'provider_id' => $provider->id,
            'service_request_id' => $request->id,
            'rating' => 4,
            'comment' => "Comment {$i}",
            'created_at' => now()->subMinutes(10 - $i),
            'updated_at' => now()->subMinutes(10 - $i),
        ]);
    }

    Sanctum::actingAs($admin);
    $latest = $this->getJson('/api/v1/admin/dashboard/overview')
        ->assertOk()
        ->json('data.latest_ratings');

    expect($latest)->toHaveCount(5);
    expect($latest[0]['id'])->toBeGreaterThan($latest[1]['id']);
    expect($latest[0])->toHaveKeys(['id', 'rating', 'comment', 'customer', 'artisan', 'service_request', 'created_at']);
});

it('ranks top artisans by average rating then rating count then completed requests', function () {
    $admin = createAdmin();
    $customer = createCustomer();
    $category = seedDashboardCategory('HVAC');

    $best = createProvider(['name' => 'Best Artisan', 'status' => 'available']);
    $mid = createProvider(['name' => 'Mid Artisan', 'status' => 'busy']);
    $busyLow = createProvider(['name' => 'Busy Low', 'status' => 'available']);
    $suspended = createProvider(['name' => 'Suspended', 'status' => 'suspended']);

    foreach ([$best, $mid, $busyLow, $suspended] as $provider) {
        $provider->categories()->attach($category->id);
    }

    $makeRatedCompleted = function (Provider $provider, int $ratingValue, int $completedCount) use ($customer, $category) {
        for ($i = 0; $i < $completedCount; $i++) {
            $request = ServiceRequest::query()->create([
                'user_id' => $customer->id,
                'provider_id' => $provider->id,
                'category_id' => $category->id,
                'description' => 'Completed work',
                'urgency' => 'normal',
                'status' => RequestStatusEnum::Completed->value,
            ]);

            if ($i === 0) {
                Rating::query()->create([
                    'user_id' => $customer->id,
                    'provider_id' => $provider->id,
                    'service_request_id' => $request->id,
                    'rating' => $ratingValue,
                    'comment' => null,
                ]);
            }
        }
    };

    // Best: avg 5, 2 ratings, 3 completed
    $req1 = ServiceRequest::query()->create([
        'user_id' => $customer->id,
        'provider_id' => $best->id,
        'category_id' => $category->id,
        'description' => 'A',
        'urgency' => 'normal',
        'status' => 'completed',
    ]);
    $req2 = ServiceRequest::query()->create([
        'user_id' => $customer->id,
        'provider_id' => $best->id,
        'category_id' => $category->id,
        'description' => 'B',
        'urgency' => 'normal',
        'status' => 'completed',
    ]);
    $req3 = ServiceRequest::query()->create([
        'user_id' => $customer->id,
        'provider_id' => $best->id,
        'category_id' => $category->id,
        'description' => 'C',
        'urgency' => 'normal',
        'status' => 'completed',
    ]);
    Rating::query()->create([
        'user_id' => $customer->id,
        'provider_id' => $best->id,
        'service_request_id' => $req1->id,
        'rating' => 5,
    ]);
    $otherCustomer = createCustomer();
    Rating::query()->create([
        'user_id' => $otherCustomer->id,
        'provider_id' => $best->id,
        'service_request_id' => $req2->id,
        'rating' => 5,
    ]);

    // Mid: avg 4, 1 rating, 1 completed
    $makeRatedCompleted($mid, 4, 1);

    // BusyLow: avg 3, 1 rating, 5 completed
    $makeRatedCompleted($busyLow, 3, 5);

    // Suspended should never appear even with high ratings
    $makeRatedCompleted($suspended, 5, 10);

    Sanctum::actingAs($admin);
    $top = $this->getJson('/api/v1/admin/dashboard/overview')
        ->assertOk()
        ->json('data.top_artisans');

    expect($top)->toHaveCount(3);
    expect($top[0]['id'])->toBe($best->id);
    expect($top[0]['average_rating'])->toBe(5);
    expect($top[0]['ratings_count'])->toBe(2);
    expect($top[0]['completed_requests_count'])->toBe(3);
    expect($top[1]['id'])->toBe($mid->id);
    expect($top[2]['id'])->toBe($busyLow->id);
    expect(collect($top)->pluck('id'))->not->toContain($suspended->id);
    expect($top[0])->not->toHaveKey('password');
    expect($top[0])->not->toHaveKey('fcm_token');
    expect($top[0])->not->toHaveKey('phone');
});

it('calculates category distribution percentages without division by zero', function () {
    $admin = createAdmin();
    $customer = createCustomer();
    $provider = createProvider();

    $plumbing = seedDashboardCategory('Plumbing Dist');
    $electrical = seedDashboardCategory('Electrical Dist');
    $unused = seedDashboardCategory('Unused Dist');

    insertServiceRequests($customer, $provider, $plumbing, 'pending', now(), 3);
    insertServiceRequests($customer, $provider, $electrical, 'completed', now(), 1);

    Sanctum::actingAs($admin);
    $distribution = $this->getJson('/api/v1/admin/dashboard/overview')
        ->assertOk()
        ->json('data.category_distribution');

    expect($distribution['total_requests'])->toBe(4);

    $byId = collect($distribution['categories'])->keyBy('category_id');
    expect($byId[$plumbing->id]['requests_count'])->toBe(3);
    expect($byId[$plumbing->id]['percentage'])->toBe(75);
    expect($byId[$electrical->id]['requests_count'])->toBe(1);
    expect($byId[$electrical->id]['percentage'])->toBe(25);
    expect($byId[$unused->id]['requests_count'])->toBe(0);
    expect($byId[$unused->id]['percentage'])->toBe(0);
});

it('returns zero category percentages when there are no requests', function () {
    $admin = createAdmin();
    seedDashboardCategory('Empty Only');

    Sanctum::actingAs($admin);
    $distribution = $this->getJson('/api/v1/admin/dashboard/overview')
        ->assertOk()
        ->json('data.category_distribution');

    expect($distribution['total_requests'])->toBe(0);
    expect($distribution['categories'][0]['percentage'])->toBe(0);
});

it('excludes soft-deleted service requests from overview counts', function () {
    $admin = createAdmin();
    $customer = createCustomer();
    $provider = createProvider();
    $category = seedDashboardCategory('Soft Delete Cat');

    $live = ServiceRequest::query()->create([
        'user_id' => $customer->id,
        'provider_id' => $provider->id,
        'category_id' => $category->id,
        'description' => 'Live',
        'urgency' => 'normal',
        'status' => 'pending',
    ]);

    $deleted = ServiceRequest::query()->create([
        'user_id' => $customer->id,
        'provider_id' => $provider->id,
        'category_id' => $category->id,
        'description' => 'Deleted',
        'urgency' => 'normal',
        'status' => 'pending',
    ]);
    $deleted->delete();

    Sanctum::actingAs($admin);
    $response = $this->getJson('/api/v1/admin/dashboard/overview')->assertOk();

    expect(collect($response->json('data.latest_requests'))->pluck('id'))
        ->toContain($live->id)
        ->not->toContain($deleted->id);
    expect($response->json('data.category_distribution.total_requests'))->toBe(1);
});

it('rejects unauthenticated monthly statistics access', function () {
    $this->getJson('/api/v1/admin/dashboard/request-statistics/monthly')->assertUnauthorized();
});

it('rejects non-admin monthly statistics access', function () {
    Sanctum::actingAs(createCustomer());
    $this->getJson('/api/v1/admin/dashboard/request-statistics/monthly')->assertForbidden();
});

it('defaults monthly statistics year to the current year', function () {
    Sanctum::actingAs(createAdmin());

    $this->getJson('/api/v1/admin/dashboard/request-statistics/monthly')
        ->assertOk()
        ->assertJsonPath('data.year', (int) now()->year);
});

it('rejects an invalid monthly statistics year', function () {
    Sanctum::actingAs(createAdmin());

    $this->getJson('/api/v1/admin/dashboard/request-statistics/monthly?year=1999')
        ->assertStatus(422);

    $this->getJson('/api/v1/admin/dashboard/request-statistics/monthly?year=not-a-year')
        ->assertStatus(422);
});

it('returns all twelve months with the documented April example percentages', function () {
    $admin = createAdmin();
    $customer = createCustomer();
    $provider = createProvider();
    $category = seedDashboardCategory('Monthly Cat');
    $april = Carbon::create(2026, 4, 15, 12, 0, 0, 'UTC');

    insertServiceRequests($customer, $provider, $category, RequestStatusEnum::Completed->value, $april, 200);
    insertServiceRequests($customer, $provider, $category, RequestStatusEnum::Cancelled->value, $april, 100);
    insertServiceRequests($customer, $provider, $category, RequestStatusEnum::Pending->value, $april, 20);

    // Outside selected year — must be excluded
    insertServiceRequests(
        $customer,
        $provider,
        $category,
        RequestStatusEnum::Completed->value,
        Carbon::create(2025, 4, 15, 12, 0, 0, 'UTC'),
        50
    );

    Sanctum::actingAs($admin);
    $payload = $this->getJson('/api/v1/admin/dashboard/request-statistics/monthly?year=2026')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($payload['year'])->toBe(2026);
    expect($payload['months'])->toHaveCount(12);
    expect(collect($payload['months'])->pluck('month')->all())->toBe(range(1, 12));

    $january = $payload['months'][0];
    expect($january['total'])->toBe(0);
    expect($january['annual_percentage'])->toBe(0);
    expect($january['statuses']['completed']['count'])->toBe(0);

    $aprilRow = $payload['months'][3];
    expect($aprilRow['month'])->toBe(4);
    expect($aprilRow['month_key'])->toBe('april');
    expect($aprilRow['total'])->toBe(320);
    expect($aprilRow['statuses']['completed']['count'])->toBe(200);
    expect($aprilRow['statuses']['cancelled']['count'])->toBe(100);
    expect($aprilRow['statuses']['pending']['count'])->toBe(20);
    expect($aprilRow['statuses']['completed']['percentage'])->toBe(62.5);
    expect($aprilRow['statuses']['cancelled']['percentage'])->toBe(31.25);
    expect($aprilRow['statuses']['pending']['percentage'])->toBe(6.25);
    expect($aprilRow['annual_percentage'])->toBe(100);

    foreach (RequestStatusEnum::cases() as $status) {
        expect($aprilRow['statuses'])->toHaveKey($status->value);
    }

    expect($payload['annual_total'])->toBe(320);
    expect($payload['year_totals']['completed'])->toBe(200);
    expect($payload['year_totals']['cancelled'])->toBe(100);
    expect($payload['year_totals']['pending'])->toBe(20);
    expect($payload['year_totals']['total'])->toBe(320);
});

it('filters monthly statistics by category and provider', function () {
    $admin = createAdmin();
    $customer = createCustomer();
    $providerA = createProvider();
    $providerB = createProvider();
    $categoryA = seedDashboardCategory('Filter A');
    $categoryB = seedDashboardCategory('Filter B');
    $march = Carbon::create(2026, 3, 10, 8, 0, 0, 'UTC');

    insertServiceRequests($customer, $providerA, $categoryA, 'completed', $march, 5);
    insertServiceRequests($customer, $providerB, $categoryA, 'pending', $march, 3);
    insertServiceRequests($customer, $providerA, $categoryB, 'cancelled', $march, 7);

    Sanctum::actingAs($admin);

    $byCategory = $this->getJson(
        "/api/v1/admin/dashboard/request-statistics/monthly?year=2026&category_id={$categoryA->id}"
    )->assertOk()->json('data');

    expect($byCategory['annual_total'])->toBe(8);
    expect($byCategory['months'][2]['statuses']['completed']['count'])->toBe(5);
    expect($byCategory['months'][2]['statuses']['pending']['count'])->toBe(3);

    $byProvider = $this->getJson(
        "/api/v1/admin/dashboard/request-statistics/monthly?year=2026&provider_id={$providerA->id}"
    )->assertOk()->json('data');

    expect($byProvider['annual_total'])->toBe(12);
});

it('rejects monthly statistics filters for missing category or soft-deleted provider', function () {
    $admin = createAdmin();
    $provider = createProvider();
    $provider->delete();

    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/dashboard/request-statistics/monthly?year=2026&category_id=999999')
        ->assertStatus(422);

    $this->getJson("/api/v1/admin/dashboard/request-statistics/monthly?year=2026&provider_id={$provider->id}")
        ->assertStatus(422);
});
