<?php

namespace App\Modules\Chat\Http\Resources;

use App\Modules\Chat\Support\ActorHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $deleted = $this->trashed();
        $sender = $this->relationLoaded('sender') ? $this->sender : null;

        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'service_request_id' => $this->service_request_id,
            'client_message_id' => $this->client_message_id,
            'type' => $this->type,
            'body' => $deleted ? null : $this->body,
            'deleted' => $deleted,
            'sender' => [
                'type' => $this->sender_type,
                'id' => $this->sender_id,
                'display_role' => $sender ? ActorHelper::displayRole($sender) : (
                    $this->sender_type === 'provider' ? 'artisan' : 'customer'
                ),
            ],
            'reply_to_message_id' => $this->reply_to_message_id,
            'created_at' => optional($this->created_at)?->toIso8601String(),
        ];
    }
}
