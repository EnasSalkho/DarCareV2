<?php
// app/Modules/Locations/Http/Resources/AddressResource.php

namespace App\Modules\Locations\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'latitude'   => $this->latitude,
            'longitude'  => $this->longitude,
            'label'      => $this->label,
            'is_primary' => $this->is_primary,
            'created_at' => $this->created_at,
        ];
    }
}
