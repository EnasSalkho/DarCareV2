<?php

namespace App\Modules\ServiceRequests\Events;

use App\Modules\ServiceRequests\Models\ServiceRequest;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class RequestStatusUpdated implements ShouldBroadcastNow
{
    use SerializesModels;

    public function __construct(public ServiceRequest $serviceRequest)
    {
    }

    public function broadcastOn(): array
    {
        // البث على قناة خاصة بالطلب نفسه
        return [
            new PrivateChannel('service-request.' . $this->serviceRequest->id),
        ];
    }

    public function broadcastWith(): array
    {
        // البيانات التي ستصل لتطبيق المستخدم.
        // الحقول الأصلية (request_id / new_status) باقية كما هي حتى لا ينكسر
        // التطبيق؛ الباقي إضافات فقط.
        return [
            'request_id' => $this->serviceRequest->id,
            'new_status' => $this->serviceRequest->status,
            'scheduled_at' => optional($this->serviceRequest->scheduled_at)->toIso8601String(),
            'provider_id' => $this->serviceRequest->provider_id,
            'user_id' => $this->serviceRequest->user_id,
            'updated_at' => optional($this->serviceRequest->updated_at)->toIso8601String(),
        ];
    }
}