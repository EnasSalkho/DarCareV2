<?php
// app/Modules/ServiceRequests/Http/Resources/ServiceRequestResource.php

namespace App\Modules\ServiceRequests\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ServiceRequestResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'user_id'      => $this->user_id,
            'provider_id'  => $this->provider_id,
            'category_id'  => $this->category_id,
            'address_id'   => $this->address_id,
            'description'  => $this->description,
            'urgency'      => $this->urgency,
            'status'       => $this->status,
            'image'        => $this->image ? asset('storage/' . $this->image) : null,
            'address'      => $this->address_id ? [
                'id'        => $this->address_id,
            ] : [
                'latitude'  => $this->temp_latitude,
                'longitude' => $this->temp_longitude,
                'label'     => $this->temp_label ?? 'temporary',
            ],
            'scheduled_at' => $this->scheduled_at,
            'created_at'   => $this->created_at,
        ];
    }
}
