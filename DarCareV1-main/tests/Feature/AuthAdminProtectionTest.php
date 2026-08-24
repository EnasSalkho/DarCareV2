<?php

use Laravel\Sanctum\Sanctum;

it('creates a sanctum token for customer login', function () {
    createCustomer(['email' => 'customer@example.com']);

    $this->postJson('/api/v1/auth/login/user', [
        'email' => 'customer@example.com',
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['token']]);
});

it('creates a sanctum token for provider login', function () {
    createProvider(['email' => 'provider@example.com']);

    $this->postJson('/api/v1/auth/login/provider', [
        'email' => 'provider@example.com',
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('success', true);
});

it('creates a sanctum token for admin login', function () {
    createAdmin(['email' => 'admin@example.com']);

    $this->postJson('/api/v1/auth/admin/login', [
        'email' => 'admin@example.com',
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['token']]);
});

it('blocks customers and providers from admin apis', function () {
    $customer = createCustomer();
    $provider = createProvider();

    Sanctum::actingAs($customer);
    $this->getJson('/api/v1/admin/users')->assertForbidden();

    Sanctum::actingAs($provider);
    $this->getJson('/api/v1/admin/users')->assertForbidden();
});

it('rejects unauthenticated admin api access', function () {
    $this->getJson('/api/v1/admin/users')->assertUnauthorized();
});

it('allows admin to access protected admin routes', function () {
    $admin = createAdmin();

    Sanctum::actingAs($admin);
    $this->getJson('/api/v1/admin/users')->assertOk();
});
