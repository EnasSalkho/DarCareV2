<?php

namespace App\Modules\Chat\Events;

use App\Modules\Chat\Models\Message;
use App\Modules\Chat\Support\ActorHelper;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.'.$this->message->conversation_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

    public function broadcastWith(): array
    {
        $sender = $this->message->sender;

        return [
            'id' => $this->message->id,
            'client_message_id' => $this->message->client_message_id,
            'conversation_id' => $this->message->conversation_id,
            'sender' => [
                'type' => $this->message->sender_type,
                'id' => $this->message->sender_id,
                'display_role' => $sender ? ActorHelper::displayRole($sender) : $this->message->sender_type,
            ],
            'type' => $this->message->type,
            'body' => $this->message->trashed() ? null : $this->message->body,
            'reply_to' => $this->message->reply_to_message_id,
            'created_at' => optional($this->message->created_at)?->toIso8601String(),
        ];
    }
}
