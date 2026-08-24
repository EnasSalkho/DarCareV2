<?php

namespace App\Modules\Cities\Services;

use App\Modules\Cities\Contracts\CityServiceInterface;
use App\Modules\Cities\Models\City;

class CityService implements CityServiceInterface
    {
        public function all()
        {
            return City::orderBy('name')->get();
        }
    }