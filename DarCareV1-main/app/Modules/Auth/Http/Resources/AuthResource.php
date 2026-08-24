<?php
// app/Modules/Auth/Http/Resources/AuthResource.php

namespace App\Modules\Auth\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AuthResource extends JsonResource
{
    private string $token;

    public function __construct($resource, string $token)
    {
        parent::__construct($resource);
        $this->token = $token;
    }

    public function toArray($request): array
    {
        return [
            'id'    => $this->id,
            'name'  => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role'  => $this->role ?? 'provider',
            'profile_image' => $this->profile_image ? 'storage/' . $this->profile_image : null,
            'token' => $this->token,
            'latitude' => $this->latitude ?? null,
            'longitude' => $this->longitude ?? null,
        ];
    }
}
