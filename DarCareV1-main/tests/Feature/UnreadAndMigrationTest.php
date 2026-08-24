<?php

use App\Modules\Chat\Contracts\UnreadMessageServiceInterface;
use App\Modules\Chat\Models\ConversationParticipant;
use App\Modules\Chat\Models\Message;
use Laravel\Sanctum\Sanctum;

it('preserves existing messages when migrating into conversations', function () {
    $customer = createCustomer();
    $provider = createProvider();
    $request = createServiceRequest($customer, $provider);

    $beforeId = Message::query()->insertGetId([
        'service_request_id' => $request->id,
        'sender_type' => 'user',
        'sender_id' => $customer->id,
        'type' => 'text',
        'body' => 'Legacy preserved message',
        'created_at' => now()->subDay()->toDateTimeString(),
        'updated_at' => now()->subDay()->toDateTimeString(),
    ]);

    $conversation = app(\App\Modules\Chat\Contracts\ConversationServiceInterface::class)
        ->openRequestConversation($request, $customer);

    $before = Message::query()->findOrFail($beforeId);

    if ($before->conversation_id === null) {
        $before->update(['conversation_id' => $conversation->id]);
    }

    expect(Message::count())->toBe(1);
    expect($before->fresh()->body)->toBe('Legacy preserved message');
    expect($before->fresh()->created_at->lte(now()->subHours(20)))->toBeTrue();
    expect(\App\Modules\Chat\Models\Conversation::where('service_request_id', $request->id)->count())->toBe(1);
});

it('tracks unread counts independently per participant', function () {
    $customer = createCustomer();
    $provider = createProvider();
    $request = createServiceRequest($customer, $provider);

    Sanctum::actingAs($customer);
    $conversationId = $this->postJson('/api/v1/chat/conversations', [
        'type' => 'request',
        'service_request_id' => $request->id,
    ])->assertOk()->json('data.id');

    Sanctum::actingAs($provider);
    $this->postJson('/api/v1/chat/conversations', [
        'type' => 'request',
        'service_request_id' => $request->id,
    ])->assertOk();

    Sanctum::actingAs($customer);
    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", ['body' => 'One'])->assertCreated();
    $this->postJson("/api/v1/chat/conversations/{$conversationId}/messages", ['body' => 'Two'])->assertCreated();

    expect(Message::where('conversation_id', $conversationId)->count())->toBe(2);
    expect(Message::where('conversation_id', $conversationId)->value('sender_type'))->toBe('user');

    $participant = ConversationParticipant::query()
        ->where('conversation_id', $conversationId)
        ->where('participant_type', 'provider')
        ->where('participant_id', $provider->id)
        ->whereNull('left_at')
        ->first();

    expect($participant)->not->toBeNull();

    $unread = app(UnreadMessageServiceInterface::class)->totalUnread($provider);
    expect($unread['total'])->toBe(2);

    Sanctum::actingAs($provider);
    $this->getJson('/api/v1/chat/unread-count')->assertOk()->assertJsonPath('data.total', 2);
    $this->patchJson("/api/v1/chat/conversations/{$conversationId}/read")->assertOk();
    $this->getJson('/api/v1/chat/unread-count')->assertOk()->assertJsonPath('data.total', 0);

    Sanctum::actingAs($customer);
    $this->getJson('/api/v1/chat/unread-count')->assertOk()->assertJsonPath('data.total', 0);
});
