<?php
// app/Modules/Favorites/Http/Resources/FavoriteResource.php

namespace App\Modules\Favorites\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FavoriteResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'provider_id' => $this->provider_id,
            'created_at'  => $this->created_at,
        ];
    }
}
