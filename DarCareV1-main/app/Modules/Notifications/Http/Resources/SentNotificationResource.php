<?php
// app/Modules/Notifications/Http/Resources/SentNotificationResource.php

namespace App\Modules\Notifications\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * سجل الإشعارات المرسلة كما يشوفه الأدمن: سطر واحد لكل إرسالة،
 * مهما كان عدد المستلمين.
 */
class SentNotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $sample = $this->resource['sample'];
        $data = is_array($sample->data) ? $sample->data : [];
        $recipient = $sample->notifiable;
        $isDirect = $this->resource['audience'] === 'specific';

        return [
            'id' => $this->resource['batch_id'],
            'type' => $sample->type,
            'audience' => $this->resource['audience'],
            'is_broadcast' => ! $isDirect,
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? $data['message'] ?? null,
            'data' => $data,
            'recipients_count' => $this->resource['recipients_count'],
            'read_count' => $this->resource['read_count'],
            // المستلم بينعرض بس لما الإشعار مرسل لشخص واحد
            'recipient' => ($isDirect && $recipient) ? [
                'id' => $recipient->id,
                'name' => $recipient->name,
                'type' => $sample->notifiable_type,
            ] : null,
            'created_at' => $sample->created_at,
        ];
    }
}
