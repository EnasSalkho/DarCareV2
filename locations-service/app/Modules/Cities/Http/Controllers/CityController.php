<?php

namespace App\Modules\Cities\Http\Controllers;

use App\Modules\Cities\Contracts\CityServiceInterface;
use App\Modules\Cities\Http\Resources\CityResource;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class CityController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly CityServiceInterface $service
    ){}

    public function index()
    {
        return $this->success(
            CityResource::collection(
                $this->service->all()
            )
        );
    }
}
