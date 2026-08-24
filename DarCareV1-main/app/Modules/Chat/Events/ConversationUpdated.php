<?php

namespace App\Modules\Chat\Events;

use App\Modules\Chat\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Conversation $conversation) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversation.'.$this->conversation->id)];
    }

    public function broadcastAs(): string
    {
        return 'ConversationUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->conversation->id,
            'type' => $this->conversation->type?->value ?? $this->conversation->type,
            'status' => $this->conversation->status?->value ?? $this->conversation->status,
            'last_message_at' => optional($this->conversation->last_message_at)?->toIso8601String(),
            'closed_at' => optional($this->conversation->closed_at)?->toIso8601String(),
        ];
    }
}
