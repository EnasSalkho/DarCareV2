<?php
// app/Modules/Ratings/Http/Controllers/RatingController.php

namespace App\Modules\Ratings\Http\Controllers;

use App\Modules\Ratings\Contracts\RatingServiceInterface;
use App\Modules\Ratings\Http\Requests\StoreRatingRequest;
use App\Modules\Ratings\Http\Resources\RatingResource;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class RatingController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly RatingServiceInterface $ratingService) {}

    public function store(StoreRatingRequest $request): JsonResponse
    {
        $rating = $this->ratingService->rateProvider($request->user()->id, $request->validated());
        return $this->created(new RatingResource($rating));
    }

    public function providerRatings(Request $request, int $providerId): JsonResponse
    {
        $ratings = $this->ratingService->getProviderRatings($providerId);
        return $this->success(RatingResource::collection($ratings));
    }
}
