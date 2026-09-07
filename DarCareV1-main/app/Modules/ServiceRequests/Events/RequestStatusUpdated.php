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
        $channels = [
            // القناة الخاصة بالطلب نفسه.
            new PrivateChannel('service-request.'.$this->serviceRequest->id),
        ];

        // القناتان التاليتان أساسيتان، وليستا تحسيناً:
        // التطبيق يشترك في قناة الطلب فقط بعد أن يصبح لديه طلب "نشط"، والطلب
        // بحالة pending ليس نشطاً. فالانتقال pending -> accepted -> on_the_way
        // كان يُبثّ على قناة لا أحد مشترك بها، ولذلك لم تكن الواجهة الرئيسية
        // تتحدث إلا بعد سحب الشاشة يدوياً. أما قناتا العميل والمزود فالتطبيق
        // مشترك بهما منذ لحظة فتحه.
        if ($this->serviceRequest->user_id) {
            $channels[] = new PrivateChannel('client.'.$this->serviceRequest->user_id);
        }

        if ($this->serviceRequest->provider_id) {
            $channels[] = new PrivateChannel('user.'.$this->serviceRequest->provider_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'RequestStatusUpdated';
    }

    public function broadcastWith(): array
    {
        // البيانات التي ستصل لتطبيق المستخدم.
        // الحقول الأصلية (request_id / new_status) باقية كما هي حتى لا ينكسر
        // التطبيق؛ الباقي إضافات فقط.
        return [
            'request_id' => $this->serviceRequest->id,
            'new_status' => $this->serviceRequest->status,
            'status' => $this->serviceRequest->status,
            'scheduled_at' => optional($this->serviceRequest->scheduled_at)->toIso8601String(),
            'provider_id' => $this->serviceRequest->provider_id,
            'user_id' => $this->serviceRequest->user_id,
            'updated_at' => optional($this->serviceRequest->updated_at)->toIso8601String(),
        ];
    }
}
