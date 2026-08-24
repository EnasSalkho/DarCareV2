<?php

use App\Modules\Notifications\Models\DeviceToken;
use Laravel\Sanctum\Sanctum;

it('registers and removes device tokens with ownership transfer', function () {
    $customerA = createCustomer();
    $customerB = createCustomer();

    Sanctum::actingAs($customerA);
    $this->postJson('/api/v1/device-tokens', [
        'token' => 'fcm-token-shared',
        'platform' => 'android',
        'device_name' => 'Phone A',
    ])->assertOk();

    expect(DeviceToken::where('token', 'fcm-token-shared')->count())->toBe(1);

    Sanctum::actingAs($customerB);
    $this->postJson('/api/v1/device-tokens', [
        'token' => 'fcm-token-shared',
        'platform' => 'android',
    ])->assertOk();

    $token = DeviceToken::where('token', 'fcm-token-shared')->first();
    expect((int) $token->tokenable_id)->toBe((int) $customerB->id);
    expect($token->tokenable_type)->toBe('user');

    Sanctum::actingAs($customerA);
    $this->deleteJson('/api/v1/device-tokens', [
        'token' => 'fcm-token-shared',
    ])->assertNotFound();

    Sanctum::actingAs($customerB);
    $this->deleteJson('/api/v1/device-tokens', [
        'token' => 'fcm-token-shared',
    ])->assertOk();
});

it('does not store chat notifications in the database and excludes sender on chat notify', function () {
    $customer = createCustomer();
    $provider = createProvider();
    $request = createServiceRequest($customer, $provider);

    Sanctum::actingAs($customer);
    $conversationId = $this->postJson('/api/v1/chat/conversations', [
        'type' => 'request',
        'service_request_id' => $request->id,
    ])->json('data.id');

    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'Notify the artisan',
    ])->assertCreated();

    expect($provider->fresh()->notifications()->where('type', 'chat_message')->count())->toBe(0);
    expect($customer->fresh()->notifications()->where('data->type', 'chat_message')->count())->toBe(0);
});

it('marks notifications read with morph ownership', function () {
    $customer = createCustomer();
    $provider = createProvider();

    $notification = $customer->notifications()->create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'type' => 'test',
        'data' => ['title' => 'Hello', 'body' => 'World', 'type' => 'test'],
    ]);

    $providerNotification = $provider->notifications()->create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'type' => 'test',
        'data' => ['title' => 'Provider', 'body' => 'Only me', 'type' => 'test'],
    ]);

    Sanctum::actingAs($customer);
    $this->patchJson('/api/v1/notifications/'.$notification->id.'/read')->assertOk();
    $this->patchJson('/api/v1/notifications/'.$providerNotification->id.'/read')->assertNotFound();
});

it('protects bulk notification endpoint', function () {
    $customer = createCustomer();
    $admin = createAdmin();

    $this->postJson('/api/v1/admin/notifications/send-bulk', [
        'target' => 'users',
        'title' => 'Hi',
        'message' => 'Hello',
    ])->assertUnauthorized();

    Sanctum::actingAs($customer);
    $this->postJson('/api/v1/admin/notifications/send-bulk', [
        'target' => 'users',
        'title' => 'Hi',
        'message' => 'Hello',
    ])->assertForbidden();

    Sanctum::actingAs($admin);
    $this->postJson('/api/v1/admin/notifications/send-bulk', [
        'target' => 'users',
        'title' => 'Hi',
        'message' => 'Hello',
    ])->assertStatus(503);
});

it('stores request status notifications synchronously without chat jobs', function () {
    Illuminate\Support\Facades\Bus::fake();

    $customer = createCustomer();
    $provider = createProvider();
    $request = createServiceRequest($customer, $provider);

    Sanctum::actingAs($provider);
    $this->patchJson('/api/v1/requests/'.$request->id.'/status', [
        'status' => 'accepted',
    ])->assertOk();

    Illuminate\Support\Facades\Bus::assertNotDispatched('App\Modules\Chat\Jobs\NotifyMessageRecipients');
    Illuminate\Support\Facades\Bus::assertNotDispatched('App\Modules\Notifications\Jobs\SendFcmNotificationJob');

    $this->assertDatabaseHas('notifications', [
        'notifiable_type' => 'user',
        'notifiable_id' => $customer->id,
        'type' => 'request_accepted',
    ]);
});
