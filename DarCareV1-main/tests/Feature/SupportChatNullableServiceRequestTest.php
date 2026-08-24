<?php

use App\Modules\Chat\Events\MessageSent;
use App\Modules\Chat\Events\UnreadCountUpdated;
use App\Modules\Chat\Models\Message;
use App\Modules\Notifications\Contracts\NotificationServiceInterface;
use App\Modules\Notifications\Events\NotificationCreated;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

it('stores request chat messages with the service request id', function () {
    $customer = createCustomer();
    $provider = createProvider();
    $request = createServiceRequest($customer, $provider);

    Sanctum::actingAs($customer);
    $conversationId = $this->postJson('/api/v1/chat/conversations', [
        'type' => 'request',
        'service_request_id' => $request->id,
    ])->assertOk()->json('data.id');

    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'Request chat body',
    ])->assertCreated()
        ->assertJsonPath('data.conversation_id', $conversationId)
        ->assertJsonPath('data.service_request_id', $request->id);

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversationId,
        'service_request_id' => $request->id,
        'body' => 'Request chat body',
    ]);
});

it('stores customer support messages with a null service_request_id', function () {
    $customer = createCustomer();

    Sanctum::actingAs($customer);
    $conversationId = $this->postJson('/api/v1/chat/conversations', [
        'type' => 'support_customer',
    ])->assertOk()->json('data.id');

    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'Need help with my account',
    ])->assertCreated()
        ->assertJsonPath('data.conversation_id', $conversationId)
        ->assertJsonPath('data.service_request_id', null);

    $message = Message::query()->where('conversation_id', $conversationId)->first();

    expect($message)->not->toBeNull()
        ->and($message->conversation_id)->toBe($conversationId)
        ->and($message->service_request_id)->toBeNull()
        ->and($message->body)->toBe('Need help with my account');
});

it('stores provider support messages with a null service_request_id', function () {
    $provider = createProvider();

    Sanctum::actingAs($provider);
    $conversationId = $this->postJson('/api/v1/chat/conversations', [
        'type' => 'support_provider',
    ])->assertOk()->json('data.id');

    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'Provider support ping',
    ])->assertCreated()
        ->assertJsonPath('data.conversation_id', $conversationId)
        ->assertJsonPath('data.service_request_id', null);

    expect(Message::query()->where('conversation_id', $conversationId)->value('service_request_id'))->toBeNull();
});

it('leaves existing request message service_request_id values unchanged when support messages are stored', function () {
    $customer = createCustomer();
    $provider = createProvider();
    $request = createServiceRequest($customer, $provider);

    Sanctum::actingAs($customer);
    $requestConversationId = $this->postJson('/api/v1/chat/conversations', [
        'type' => 'request',
        'service_request_id' => $request->id,
    ])->assertOk()->json('data.id');

    $this->postJson("/api/v1/chat/conversations/{$requestConversationId}/messages", [
        'body' => 'Keep this request id',
        'client_message_id' => 'keep-request-1',
    ])->assertCreated();

    $requestMessageId = Message::query()
        ->where('conversation_id', $requestConversationId)
        ->where('client_message_id', 'keep-request-1')
        ->value('id');

    $supportConversationId = $this->postJson('/api/v1/chat/conversations', [
        'type' => 'support_customer',
    ])->assertOk()->json('data.id');

    $this->postJson("/api/v1/chat/conversations/{$supportConversationId}/messages", [
        'body' => 'Support should be null',
    ])->assertCreated();

    $this->assertDatabaseHas('messages', [
        'id' => $requestMessageId,
        'conversation_id' => $requestConversationId,
        'service_request_id' => $request->id,
        'body' => 'Keep this request id',
    ]);

    expect(Message::query()->where('conversation_id', $supportConversationId)->value('service_request_id'))->toBeNull();
});

it('sends request chat with pusher and fcm immediately and does not persist chat notifications or jobs', function () {
    Event::fake([MessageSent::class, NotificationCreated::class, UnreadCountUpdated::class]);
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
                    && ($data['type'] ?? null) === 'chat_message';
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
        'body' => 'Request notify path',
    ])->assertCreated();

    expect(Message::where('conversation_id', $conversationId)->count())->toBe(1);

    Event::assertDispatched(MessageSent::class, function (MessageSent $event) {
        return $event instanceof ShouldBroadcastNow
            && $event->broadcastAs() === 'MessageSent';
    });
    Event::assertDispatched(UnreadCountUpdated::class);
    Event::assertNotDispatched(NotificationCreated::class);

    Bus::assertNotDispatched('App\Modules\Chat\Jobs\NotifyMessageRecipients');
    Bus::assertNotDispatched('App\Modules\Notifications\Jobs\SendFcmNotificationJob');

    $this->assertDatabaseMissing('notifications', [
        'type' => 'chat_message',
    ]);
});

it('sends support chat with pusher and fcm immediately and does not persist chat notifications or jobs', function () {
    Event::fake([MessageSent::class, NotificationCreated::class, UnreadCountUpdated::class]);
    Bus::fake();

    $admin = createAdmin();
    $customer = createCustomer();

    $this->mock(NotificationServiceInterface::class, function ($mock) use ($admin) {
        $mock->shouldNotReceive('storeDatabaseNotification');
        $mock->shouldReceive('sendPushImmediately')
            ->once()
            ->withArgs(function ($notifiable, $type, $data) use ($admin) {
                return $notifiable->is($admin)
                    && $type === 'chat_message'
                    && ($data['type'] ?? null) === 'chat_message'
                    && ! array_key_exists('service_request_id', $data);
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
        'type' => 'support_customer',
    ])->json('data.id');

    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'Support notify path',
        'client_message_id' => 'support-notify-1',
    ])->assertCreated();

    expect(Message::where('conversation_id', $conversationId)->value('service_request_id'))->toBeNull();

    Event::assertDispatched(MessageSent::class, function (MessageSent $event) {
        return $event instanceof ShouldBroadcastNow
            && $event->broadcastAs() === 'MessageSent';
    });
    Event::assertDispatched(UnreadCountUpdated::class);
    Event::assertNotDispatched(NotificationCreated::class);

    Bus::assertNotDispatched('App\Modules\Chat\Jobs\NotifyMessageRecipients');
    Bus::assertNotDispatched('App\Modules\Notifications\Jobs\SendFcmNotificationJob');

    expect($admin->fresh()->notifications()->where('type', 'chat_message')->count())->toBe(0);
    expect($customer->fresh()->notifications()->where('type', 'chat_message')->count())->toBe(0);
});

it('keeps support message idempotency for the same client_message_id', function () {
    $customer = createCustomer();

    Sanctum::actingAs($customer);
    $conversationId = $this->postJson('/api/v1/chat/conversations', [
        'type' => 'support_customer',
    ])->json('data.id');

    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'Same support message',
        'client_message_id' => 'support-idem-1',
    ])->assertCreated();

    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'Same support message',
        'client_message_id' => 'support-idem-1',
    ])->assertCreated();

    expect(Message::where('conversation_id', $conversationId)->count())->toBe(1);
});

it('denies unrelated actors from support and request conversations while allowing participants', function () {
    $customer = createCustomer();
    $otherCustomer = createCustomer();
    $provider = createProvider();
    $otherProvider = createProvider();
    $request = createServiceRequest($customer, $provider);

    Sanctum::actingAs($customer);
    $requestConversationId = $this->postJson('/api/v1/chat/conversations', [
        'type' => 'request',
        'service_request_id' => $request->id,
    ])->json('data.id');

    $supportConversationId = $this->postJson('/api/v1/chat/conversations', [
        'type' => 'support_customer',
    ])->json('data.id');

    $this->getJson("/api/v1/chat/conversations/{$requestConversationId}")->assertOk();
    $this->getJson("/api/v1/chat/conversations/{$supportConversationId}")->assertOk();

    Sanctum::actingAs($provider);
    $this->getJson("/api/v1/chat/conversations/{$requestConversationId}")->assertOk();
    $this->getJson("/api/v1/chat/conversations/{$supportConversationId}")->assertForbidden();

    Sanctum::actingAs($otherCustomer);
    $this->getJson("/api/v1/chat/conversations/{$requestConversationId}")->assertForbidden();
    $this->getJson("/api/v1/chat/conversations/{$supportConversationId}")->assertForbidden();
    $this->postJson("/api/v1/chat/conversations/{$supportConversationId}/messages", [
        'body' => 'Intruder support',
    ])->assertForbidden();

    Sanctum::actingAs($otherProvider);
    $this->getJson("/api/v1/chat/conversations/{$requestConversationId}")->assertForbidden();

    Sanctum::actingAs($provider);
    $providerSupportId = $this->postJson('/api/v1/chat/conversations', [
        'type' => 'support_provider',
    ])->json('data.id');

    Sanctum::actingAs($otherProvider);
    $this->getJson("/api/v1/chat/conversations/{$providerSupportId}")->assertForbidden();
});

it('removes the previous provider from request chat after reassignment', function () {
    $customer = createCustomer();
    $oldProvider = createProvider();
    $newProvider = createProvider();
    $admin = createAdmin();
    $request = createServiceRequest($customer, $oldProvider);

    Sanctum::actingAs($customer);
    $conversationId = $this->postJson('/api/v1/chat/conversations', [
        'type' => 'request',
        'service_request_id' => $request->id,
    ])->json('data.id');

    Sanctum::actingAs($admin);
    $this->patchJson('/api/v1/admin/service-requests/'.$request->id.'/reassign', [
        'provider_id' => $newProvider->id,
    ])->assertOk();

    Sanctum::actingAs($oldProvider);
    $this->getJson("/api/v1/chat/conversations/{$conversationId}")->assertForbidden();

    Sanctum::actingAs($newProvider);
    $this->getJson("/api/v1/chat/conversations/{$conversationId}")->assertOk();
});

it('makes messages.service_request_id nullable while preserving existing values and foreign keys', function () {
    $column = collect(Schema::getColumns('messages'))->firstWhere('name', 'service_request_id');

    expect($column)->not->toBeNull()
        ->and((bool) $column['nullable'])->toBeTrue();

    $foreignKeys = collect(Schema::getForeignKeys('messages'))
        ->filter(fn (array $key) => in_array('service_request_id', $key['columns'], true));

    expect($foreignKeys)->not->toBeEmpty();
});

it('refuses rollback of the nullable migration when support messages exist', function () {
    $customer = createCustomer();

    Sanctum::actingAs($customer);
    $conversationId = $this->postJson('/api/v1/chat/conversations', [
        'type' => 'support_customer',
    ])->json('data.id');

    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'Blocks unsafe rollback',
    ])->assertCreated();

    $migration = include database_path('migrations/2026_08_23_120000_make_messages_service_request_id_nullable.php');

    expect(fn () => $migration->down())->toThrow(RuntimeException::class);

    expect(Message::where('conversation_id', $conversationId)->count())->toBe(1);
    expect(Message::where('conversation_id', $conversationId)->value('service_request_id'))->toBeNull();
});

it('rolls back the nullable migration only when every message still has a service request id', function () {
    $customer = createCustomer();
    $provider = createProvider();
    $request = createServiceRequest($customer, $provider);

    Sanctum::actingAs($customer);
    $conversationId = $this->postJson('/api/v1/chat/conversations', [
        'type' => 'request',
        'service_request_id' => $request->id,
    ])->json('data.id');

    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'Safe to revert column',
    ])->assertCreated();

    $migration = include database_path('migrations/2026_08_23_120000_make_messages_service_request_id_nullable.php');
    $migration->down();

    $column = collect(Schema::getColumns('messages'))->firstWhere('name', 'service_request_id');
    expect((bool) $column['nullable'])->toBeFalse();

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversationId,
        'service_request_id' => $request->id,
        'body' => 'Safe to revert column',
    ]);

    $migration->up();
});
