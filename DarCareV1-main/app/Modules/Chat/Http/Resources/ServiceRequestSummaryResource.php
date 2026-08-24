<?php

namespace App\Modules\Chat\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceRequestSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'urgency' => $this->urgency,
            'description' => mb_substr((string) $this->description, 0, 120),
            'provider_id' => $this->provider_id,
            'user_id' => $this->user_id,
        ];
    }
}
