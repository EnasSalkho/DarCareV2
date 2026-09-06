<?php

namespace App\Modules\ServiceRequests\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Services\LocationClient;

class ServiceRequestResource extends JsonResource
{
    public function toArray($request): array
    {
        // محاولة جلب العنوان عبر LocationClient إذا كان الـ address_id موجوداً ولم يتم تعيينه مسبقاً
        $resolvedAddress = $this->address ?? null;

        if (! $resolvedAddress && $this->address_id) {
            try {
                $locationClient = app(LocationClient::class);
                $resolvedAddress = $locationClient->getAddress($this->address_id);
            } catch (\Throwable $e) {
                // في حال فشل الاتصال بخدمة المواقع، نعود للاستدلال بالمعرف على الأقل
                $resolvedAddress = ['id' => $this->address_id];
            }
        }

        // إذا لم يوجد address_id، نتحقق من وجود إحداثيات مؤقتة (Temporary Address)
        if (! $resolvedAddress && ($this->temp_latitude || $this->temp_longitude)) {
            $resolvedAddress = [
                'latitude'  => $this->temp_latitude,
                'longitude' => $this->temp_longitude,
                'label'     => $this->temp_label ?? 'temporary',
            ];
        }

        return [
            'id'           => $this->id,
            'user_id'      => $this->user_id,
            'provider_id'  => $this->provider_id,
            'category_id'  => $this->category_id,
            'address_id'   => $this->address_id,
            'description'  => $this->description,
            'urgency'      => $this->urgency,
            'status'       => $this->status,
            'image'        => $this->image
                ? asset('storage/' . $this->image)
                : null,
            'address'      => $resolvedAddress,
            'scheduled_at' => $this->scheduled_at,
            'created_at'   => $this->created_at,
        ];
    }
}