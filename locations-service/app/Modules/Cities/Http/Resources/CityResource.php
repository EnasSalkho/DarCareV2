<?php

namespace App\Modules\Cities\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CityResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'=>$this->id,
            'name' => app()->getLocale() === 'ar'? $this->name_ar: $this->name,
        ];
    }
}