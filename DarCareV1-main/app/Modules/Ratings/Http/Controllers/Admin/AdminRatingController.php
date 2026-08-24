<?php

namespace App\Modules\Ratings\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Ratings\Contracts\RatingServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminRatingController extends Controller
{
    public function __construct(
        private readonly RatingServiceInterface $ratingService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $ratings = $this->ratingService->getAllRatingsForAdmin(
          $request->query('rating'),
            $request->query('provider_id')
        );

        return response()->json([
            'status' => 'success',
            'data' => $ratings
        ], 200);
    }

    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->ratingService->deleteRating($id);

        if (!$deleted) {
            return response()->json([
                'status' => 'error',
                'message' => 'التقييم غير موجود'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'تم حذف التقييم بنجاح'
        ], 200);
    }
}
