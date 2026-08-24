<?php
// app/Modules/Providers/Http/Resources/ProviderResource.php

namespace App\Modules\Providers\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProviderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'email'               => $this->email,
            'phone'               => $this->phone,
            'bio'                 => $this->bio,
            'years_of_experience' => $this->years_of_experience,
            'status'              => $this->status,
            'rating_avg'          => $this->rating_avg,
            'profile_image' => $this->profile_image ? 'storage/' . $this->profile_image : null,
            'categories' => $this->whenLoaded('categories', fn() =>
                $this->categories->map(fn($c) => ['id' => $c->id, 'name' => $c->name])
            ),
            'addresses' => $this->whenLoaded('addresses', fn() =>
                $this->addresses->map(fn($a) => [
                    'id'         => $a->id,
                    'latitude'   => $a->latitude,
                    'longitude'  => $a->longitude,
                    'label'      => $a->label,
                    'is_primary' => $a->is_primary,
                ])
            ),
            'created_at' => $this->created_at,
            'latitude' => $this->latitude,
        'longitude' => $this->longitude,
        ];
    }
}
        