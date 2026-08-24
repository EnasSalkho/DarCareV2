<?php
// app/Modules/Ratings/Http/Resources/RatingResource.php

namespace App\Modules\Ratings\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RatingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                 => $this->id,
            'user_id'            => $this->user_id,
            'provider_id'        => $this->provider_id,
            'service_request_id' => $this->service_request_id,
            'rating'             => $this->rating,
            'comment'            => $this->comment,
            'created_at'         => $this->created_at,
        ];
    }
}
