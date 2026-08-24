<?php

use App\Modules\Chat\Events\MessageSent;
use App\Modules\Chat\Events\UnreadCountUpdated;
use App\Modules\Chat\Models\ConversationParticipant;
use App\Modules\Chat\Models\Message;
use App\Modules\Notifications\Contracts\NotificationServiceInterface;
use App\Modules\Notifications\Events\NotificationCreated;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

it('broadcasts MessageSent with ShouldBroadcastNow and does not dispatch chat notification jobs', function () {
    Event::fake([MessageSent::class, NotificationCreated::class, UnreadCountUpdated::class]);
    Bus::fake();

    $customer = createCustomer();
    $provider = createProvider();
    $request = createServiceRequest($customer, $provider);

    Sanctum::actingAs($customer);
    $conversationId = $this->postJson('/api/v1/chat/conversations', [
        'type' => 'request',
        'service_request_id' => $request->id,
    ])->json('data.id');

    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'Sync hello',
        'client_message_id' => 'sync-1',
    ])->assertCreated();

    Event::assertDispatched(MessageSent::class, function (MessageSent $event) {
        return $event instanceof ShouldBroadcastNow
            && $event->broadcastAs() === 'MessageSent';
    });

    Event::assertNotDispatched(NotificationCreated::class);

    // Framework may wrap broadcasts; assert chat/notification jobs specifically are gone.
    Bus::assertNotDispatched('App\Modules\Chat\Jobs\NotifyMessageRecipients');
    Bus::assertNotDispatched('App\Modules\Notifications\Jobs\SendFcmNotificationJob');
    expect(Message::where('conversation_id', $conversationId)->count())->toBe(1);
});

it('does not store database notifications when sending a chat message', function () {
    Bus::fake();

    $customer = createCustomer();
    $provider = createProvider();
    $request = createServiceRequest($customer, $provider);

    Sanctum::actingAs($customer);
    $conversationId = $this->postJson('/api/v1/chat/conversations', [
        'type' => 'request',
        'service_request_id' => $request->id,
    ])->json('data.id');

    $response = $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'Notify artisan now',
    ]);

    $response->assertCreated();
    Bus::assertNotDispatched('App\Modules\Chat\Jobs\NotifyMessageRecipients');
    Bus::assertNotDispatched('App\Modules\Notifications\Jobs\SendFcmNotificationJob');

    $messageId = Message::where('conversation_id', $conversationId)->value('id');

    $this->assertDatabaseHas('messages', [
        'id' => $messageId,
        'conversation_id' => $conversationId,
    ]);

    $this->assertDatabaseMissing('notifications', [
        'notifiable_type' => 'provider',
        'notifiable_id' => $provider->id,
        'type' => 'chat_message',
    ]);

    expect($provider->fresh()->notifications()->where('type', 'chat_message')->count())->toBe(0);
    expect($customer->fresh()->notifications()->where('type', 'chat_message')->count())->toBe(0);
});

it('sends FCM to the recipient immediately without persisting a database notification', function () {
    Bus::fake();

    $customer = createCustomer();
    $provider = createProvider();
    $request = createServiceRequest($customer, $provider);

    $this->mock(NotificationServiceInterface::class, function ($mock) use ($provider) {
        $mock->shouldNotReceive('storeDatabaseNotification');
        $mock->shouldReceive('sendPushImmediately')
            ->once()
            ->withArgs(function ($notifiable, $type, $data) use ($provider) {
                return $notifiable->is($provider)
                    && $type === 'chat_message'
                    && $data['type'] === 'chat_message'
                    && isset($data['conversation_id'])
                    && isset($data['message_id'])
                    && $data['route'] === 'chat';
            })
            ->andReturn([
                'attempted' => 1,
                'sent' => 1,
                'failed' => 0,
                'invalidated' => 0,
            ]);
    });

    Sanctum::actingAs($customer);
    $conversationId = $this->postJson('/api/v1/chat/conversations', [
        'type' => 'request',
        'service_request_id' => $request->id,
    ])->json('data.id');

    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'FCM only',
    ])->assertCreated();
});

it('broadcasts UnreadCountUpdated without NotificationCreated for chat messages', function () {
    Event::fake([MessageSent::class, NotificationCreated::class, UnreadCountUpdated::class]);
    Bus::fake();

    $customer = createCustomer();
    $provider = createProvider();
    $request = createServiceRequest($customer, $provider);

    Sanctum::actingAs($customer);
    $conversationId = $this->postJson('/api/v1/chat/conversations', [
        'type' => 'request',
        'service_request_id' => $request->id,
    ])->json('data.id');

    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'Unread fan-out',
    ])->assertCreated();

    Event::assertDispatched(UnreadCountUpdated::class);
    Event::assertNotDispatched(NotificationCreated::class);
});

it('excludes muted recipients from synchronous chat notification fan-out', function () {
    Bus::fake();

    $customer = createCustomer();
    $provider = createProvider();
    $request = createServiceRequest($customer, $provider);

    Sanctum::actingAs($customer);
    $conversationId = $this->postJson('/api/v1/chat/conversations', [
        'type' => 'request',
        'service_request_id' => $request->id,
    ])->json('data.id');

    Sanctum::actingAs($provider);
    $this->postJson('/api/v1/chat/conversations', [
        'type' => 'request',
        'service_request_id' => $request->id,
    ])->assertOk();

    $this->patchJson("/api/v1/chat/conversations/{$conversationId}/mute", [
        'muted' => true,
    ])->assertOk();

    expect(
        ConversationParticipant::query()
            ->where('conversation_id', $conversationId)
            ->where('participant_type', 'provider')
            ->where('participant_id', $provider->id)
            ->whereNotNull('muted_at')
            ->exists()
    )->toBeTrue();

    Sanctum::actingAs($customer);
    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'Muted should not notify',
    ])->assertCreated();

    expect($provider->fresh()->notifications()->where('type', 'chat_message')->count())->toBe(0);
    Bus::assertNotDispatched('App\Modules\Chat\Jobs\NotifyMessageRecipients');
    Bus::assertNotDispatched('App\Modules\Notifications\Jobs\SendFcmNotificationJob');
});

it('keeps the stored message when Firebase push is unavailable', function () {
    Bus::fake();

    $this->mock(NotificationServiceInterface::class, function ($mock) {
        $mock->shouldNotReceive('storeDatabaseNotification');
        $mock->shouldReceive('sendPushImmediately')->andReturn([
            'attempted' => 1,
            'sent' => 0,
            'failed' => 1,
            'invalidated' => 0,
        ]);
        $mock->shouldReceive('hasFirebaseCredentials')->andReturn(false);
    });

    $customer = createCustomer();
    $provider = createProvider();
    $request = createServiceRequest($customer, $provider);

    Sanctum::actingAs($customer);
    $conversationId = $this->postJson('/api/v1/chat/conversations', [
        'type' => 'request',
        'service_request_id' => $request->id,
    ])->json('data.id');

    $response = $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'Survives FCM failure',
    ]);

    $response->assertCreated();
    expect(Message::where('conversation_id', $conversationId)->where('body', 'Survives FCM failure')->exists())->toBeTrue();
    Bus::assertNotDispatched('App\Modules\Chat\Jobs\NotifyMessageRecipients');
    Bus::assertNotDispatched('App\Modules\Notifications\Jobs\SendFcmNotificationJob');
});

it('enforces synchronous bulk recipient limit and never dispatches chat jobs', function () {
    Bus::fake();

    $admin = createAdmin();
    Sanctum::actingAs($admin);

    // Without Firebase credentials the endpoint still fails closed for bulk.
    $this->postJson('/api/v1/admin/notifications/send-bulk', [
        'target' => 'users',
        'title' => 'Hi',
        'message' => 'Hello',
    ])->assertStatus(503);

    Bus::assertNotDispatched('App\Modules\Chat\Jobs\NotifyMessageRecipients');
    Bus::assertNotDispatched('App\Modules\Notifications\Jobs\SendFcmNotificationJob');
    expect(NotificationService::BULK_RECIPIENT_LIMIT)->toBe(100);
});

it('does not implement queued ShouldBroadcast alone on chat events', function () {
    $messageSent = new ReflectionClass(MessageSent::class);
    expect($messageSent->implementsInterface(ShouldBroadcastNow::class))->toBeTrue();

    $notificationCreated = new ReflectionClass(NotificationCreated::class);
    expect($notificationCreated->implementsInterface(ShouldBroadcastNow::class))->toBeTrue();

    $unread = new ReflectionClass(UnreadCountUpdated::class);
    expect($unread->implementsInterface(ShouldBroadcastNow::class))->toBeTrue();
});
