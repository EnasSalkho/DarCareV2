<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

function createCustomer(array $overrides = []): \App\Modules\Users\Models\User
{
    return \App\Modules\Users\Models\User::factory()->create(array_merge([
        'role' => 'user',
        'password' => bcrypt('password'),
    ], $overrides));
}

function createAdmin(array $overrides = []): \App\Modules\Users\Models\User
{
    return \App\Modules\Users\Models\User::factory()->admin()->create(array_merge([
        'password' => bcrypt('password'),
    ], $overrides));
}

function createProvider(array $overrides = []): \App\Modules\Providers\Models\Provider
{
    return \App\Modules\Providers\Models\Provider::query()->create(array_merge([
        'name' => fake()->name(),
        'phone' => fake()->unique()->numerify('09########'),
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password'),
        'years_of_experience' => 3,
        'bio' => 'Test provider',
        'profile_image' => 'providers/images/test.jpg',
        'status' => 'available',
        'rating_avg' => 0,
    ], $overrides));
}

function createCategory(): \App\Modules\Categories\Models\Category
{
    $name = 'Plumbing '.fake()->unique()->numerify('###');

    return \App\Modules\Categories\Models\Category::query()->create([
        'name' => $name,
        'slug' => \Illuminate\Support\Str::slug($name),
        'is_active' => true,
    ]);
}

function createServiceRequest(
    \App\Modules\Users\Models\User $customer,
    \App\Modules\Providers\Models\Provider $provider,
    array $overrides = []
): \App\Modules\ServiceRequests\Models\ServiceRequest {
    $category = createCategory();

    return \App\Modules\ServiceRequests\Models\ServiceRequest::query()->create(array_merge([
        'user_id' => $customer->id,
        'provider_id' => $provider->id,
        'category_id' => $category->id,
        'description' => 'Need help with a leak',
        'urgency' => 'normal',
        'status' => 'pending',
    ], $overrides));
}

function bearer(\Illuminate\Foundation\Auth\User $actor): array
{
    $token = $actor->createToken('test')->plainTextToken;

    return ['Authorization' => 'Bearer '.$token];
}
