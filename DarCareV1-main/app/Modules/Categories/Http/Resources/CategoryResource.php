<?php
// app/Modules/Categories/Http/Resources/CategoryResource.php

namespace App\Modules\Categories\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'slug'        => $this->slug,
            'icon'        => $this->icon,
            'description' => $this->description,
        ];
    }
}
