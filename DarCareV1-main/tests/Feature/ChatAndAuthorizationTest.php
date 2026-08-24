<?php

use App\Modules\Chat\Events\MessageSent;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\Message;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

it('allows customer to view own request and denies other customer', function () {
    $owner = createCustomer();
    $other = createCustomer();
    $provider = createProvider();
    $request = createServiceRequest($owner, $provider);

    Sanctum::actingAs($owner);
    $this->getJson('/api/v1/requests/'.$request->id)->assertOk();

    Sanctum::actingAs($other);
    $this->getJson('/api/v1/requests/'.$request->id)->assertNotFound();
});

it('allows assigned provider and denies unrelated provider', function () {
    $customer = createCustomer();
    $assigned = createProvider();
    $other = createProvider();
    $request = createServiceRequest($customer, $assigned);

    Sanctum::actingAs($assigned);
    $this->getJson('/api/v1/requests/'.$request->id)->assertOk();

    Sanctum::actingAs($other);
    $this->getJson('/api/v1/requests/'.$request->id)->assertNotFound();
});

it('opens a single request conversation for customer and provider', function () {
    $customer = createCustomer();
    $provider = createProvider();
    $request = createServiceRequest($customer, $provider);

    Sanctum::actingAs($customer);
    $first = $this->postJson('/api/v1/chat/conversations', [
        'type' => 'request',
        'service_request_id' => $request->id,
    ])->assertOk()->json('data.id');

    Sanctum::actingAs($provider);
    $second = $this->postJson('/api/v1/chat/conversations', [
        'type' => 'request',
        'service_request_id' => $request->id,
    ])->assertOk()->json('data.id');

    expect($first)->toBe($second);
    expect(Conversation::where('type', 'request')->where('service_request_id', $request->id)->count())->toBe(1);
});

it('denies unrelated actors from opening request conversations', function () {
    $customer = createCustomer();
    $provider = createProvider();
    $stranger = createCustomer();
    $request = createServiceRequest($customer, $provider);

    Sanctum::actingAs($stranger);
    $this->postJson('/api/v1/chat/conversations', [
        'type' => 'request',
        'service_request_id' => $request->id,
    ])->assertForbidden();
});

it('allows customer support and provider support with role checks', function () {
    $customer = createCustomer();
    $provider = createProvider();

    Sanctum::actingAs($customer);
    $this->postJson('/api/v1/chat/conversations', ['type' => 'support_customer'])->assertOk();
    $this->postJson('/api/v1/chat/conversations', ['type' => 'support_provider'])->assertForbidden();

    Sanctum::actingAs($provider);
    $this->postJson('/api/v1/chat/conversations', ['type' => 'support_provider'])->assertOk();
    $this->postJson('/api/v1/chat/conversations', ['type' => 'support_customer'])->assertForbidden();
});

it('lets participants send messages and blocks non participants', function () {
    Event::fake([MessageSent::class]);

    $customer = createCustomer();
    $provider = createProvider();
    $stranger = createCustomer();
    $request = createServiceRequest($customer, $provider);

    Sanctum::actingAs($customer);
    $conversationId = $this->postJson('/api/v1/chat/conversations', [
        'type' => 'request',
        'service_request_id' => $request->id,
    ])->json('data.id');

    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'Hello artisan',
        'client_message_id' => 'msg-1',
    ])->assertCreated();

    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'Hello artisan',
        'client_message_id' => 'msg-1',
    ])->assertCreated();

    expect(Message::where('conversation_id', $conversationId)->count())->toBe(1);

    Sanctum::actingAs($stranger);
    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'Intruder',
    ])->assertForbidden();

    Event::assertDispatched(MessageSent::class);
});

it('rejects blank and overlong messages', function () {
    $customer = createCustomer();
    $provider = createProvider();
    $request = createServiceRequest($customer, $provider);

    Sanctum::actingAs($customer);
    $conversationId = $this->postJson('/api/v1/chat/conversations', [
        'type' => 'request',
        'service_request_id' => $request->id,
    ])->json('data.id');

    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => '   ',
    ])->assertStatus(422);

    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => str_repeat('a', 5001),
    ])->assertStatus(422);
});

it('makes final status conversations read only', function () {
    $customer = createCustomer();
    $provider = createProvider();
    $request = createServiceRequest($customer, $provider);

    Sanctum::actingAs($customer);
    $conversationId = $this->postJson('/api/v1/chat/conversations', [
        'type' => 'request',
        'service_request_id' => $request->id,
    ])->json('data.id');

    Sanctum::actingAs($provider);
    $this->patchJson('/api/v1/requests/'.$request->id.'/status', [
        'status' => 'rejected',
    ])->assertOk();

    Sanctum::actingAs($customer);
    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'Should fail',
    ])->assertForbidden();

    $this->getJson("/api/v1/chat/conversations/{$conversationId}/messages")->assertOk();
});

it('removes previous provider access after reassignment', function () {
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

    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", [
        'body' => 'History',
    ])->assertCreated();

    Sanctum::actingAs($admin);
    $this->patchJson('/api/v1/admin/service-requests/'.$request->id.'/reassign', [
        'provider_id' => $newProvider->id,
    ])->assertOk();

    Sanctum::actingAs($oldProvider);
    $this->getJson("/api/v1/chat/conversations/{$conversationId}/messages")->assertForbidden();

    Sanctum::actingAs($newProvider);
    $this->getJson("/api/v1/chat/conversations/{$conversationId}/messages")
        ->assertOk()
        ->assertJsonPath('data.data.0.body', 'History');
});

it('secures legacy message routes with conversation authorization', function () {
    $customer = createCustomer();
    $provider = createProvider();
    $stranger = createCustomer();
    $request = createServiceRequest($customer, $provider);

    Sanctum::actingAs($customer);
    $this->postJson('/api/v1/requests/'.$request->id.'/messages', [
        'body' => 'Legacy hello',
    ])->assertCreated()->assertJsonPath('meta.deprecated', true);

    Sanctum::actingAs($stranger);
    $this->getJson('/api/v1/requests/'.$request->id.'/messages')->assertForbidden();
});

it('authorizes conversation access by participant morph type and uses private channels', function () {
    $customer = createCustomer();
    $provider = createProvider();
    $request = createServiceRequest($customer, $provider);
    $conversation = app(\App\Modules\Chat\Contracts\ConversationServiceInterface::class)
        ->openRequestConversation($request, $customer);

    $policy = app(\App\Policies\ConversationPolicy::class);

    expect($policy->view($customer, $conversation))->toBeTrue();
    expect($policy->view($provider, $conversation))->toBeTrue();
    expect($policy->view(createCustomer(), $conversation))->toBeFalse();
    expect($policy->view(createProvider(), $conversation))->toBeFalse();

    $message = Message::query()->create([
        'conversation_id' => $conversation->id,
        'service_request_id' => $request->id,
        'sender_type' => 'user',
        'sender_id' => $customer->id,
        'type' => 'text',
        'body' => 'Channel payload check',
    ]);

    $event = new \App\Modules\Chat\Events\MessageSent($message);
    $channels = $event->broadcastOn();

    expect($channels[0])->toBeInstanceOf(\Illuminate\Broadcasting\PrivateChannel::class);
    expect($channels[0]->name)->toBe('private-conversation.'.$conversation->id);

    $payload = $event->broadcastWith();
    expect($payload)->toHaveKeys(['id', 'conversation_id', 'sender', 'body', 'type']);
    expect($payload)->not->toHaveKey('password');
});
