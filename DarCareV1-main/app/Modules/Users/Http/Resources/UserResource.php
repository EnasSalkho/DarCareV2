<?php
// app/Modules/Users/Http/Resources/UserResource.php

namespace App\Modules\Users\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'email'         => $this->email,
            'phone'         => $this->phone,
            'role'          => $this->role,
            'profile_image' => $this->profile_image ? 'storage/' . $this->profile_image : null,
            'addresses'     => $this->whenLoaded('addresses', fn() =>
                $this->addresses->map(fn($a) => [
                    'id'         => $a->id,
                    'latitude'   => $a->latitude,
                    'longitude'  => $a->longitude,
                    'label'      => $a->label,
                    'is_primary' => $a->is_primary,
                ])
            ),
            'created_at' => $this->created_at,
        ];
    }
}
