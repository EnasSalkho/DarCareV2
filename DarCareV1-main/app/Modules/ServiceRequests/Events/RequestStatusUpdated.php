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
        // البيانات التي ستصل لتطبيق المستخدم
        return [
            'request_id' => $this->serviceRequest->id,
            'new_status' => $this->serviceRequest->status,
        ];
    }
}