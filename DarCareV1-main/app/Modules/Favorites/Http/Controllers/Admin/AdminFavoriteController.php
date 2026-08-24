<?php

namespace App\Modules\Favorites\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Favorites\Contracts\FavoriteServiceInterface;
use Illuminate\Http\JsonResponse;

class AdminFavoriteController extends Controller
{
    public function __construct(
        private readonly FavoriteServiceInterface $favoriteService
    ) {}

    public function getTopFavorited(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->favoriteService->getMostFavoritedProviders()
        ], 200);
    }
}
