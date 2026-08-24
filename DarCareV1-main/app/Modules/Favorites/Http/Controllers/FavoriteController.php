<?php
// app/Modules/Favorites/Http/Controllers/FavoriteController.php

namespace App\Modules\Favorites\Http\Controllers;

use App\Modules\Favorites\Contracts\FavoriteServiceInterface;
use App\Modules\Favorites\Http\Resources\FavoriteResource;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class FavoriteController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly FavoriteServiceInterface $favoriteService) {}

    public function index(Request $request): JsonResponse
    {
        $favorites = $this->favoriteService->list($request->user()->id);
        return $this->success(FavoriteResource::collection($favorites));
    }

    public function toggle(Request $request, int $providerId): JsonResponse
    {
        $result = $this->favoriteService->toggle($request->user()->id, $providerId);
        return $this->success($result, ucfirst($result['action']) . ' from favorites');
    }
}
