<?php
// app/Modules/Notifications/Http/Resources/NotificationResource.php

namespace App\Modules\Notifications\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = is_array($this->data) ? $this->data : [];

        return [
            'id' => $this->id,
            'type' => $data['type'] ?? $this->type,
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? $data['message'] ?? null,
            'data' => $data,
            'route' => $data['route'] ?? null,
            'conversation_id' => isset($data['conversation_id']) ? (int) $data['conversation_id'] : null,
            'message_id' => isset($data['message_id']) ? (int) $data['message_id'] : null,
            'service_request_id' => isset($data['service_request_id'])
                ? (int) $data['service_request_id']
                : (isset($data['request_id']) ? (int) $data['request_id'] : null),
            // بيظهر بس بسجل الأدمن (لما تنجلب علاقة المستلم)
            'recipient' => $this->whenLoaded('notifiable', fn () => $this->notifiable ? [
                'id' => $this->notifiable->id,
                'name' => $this->notifiable->name,
                'type' => $this->notifiable_type,
            ] : null),
            'read_at' => $this->read_at,
            'is_read' => $this->read_at !== null,
            'created_at' => $this->created_at,
        ];
    }
}
