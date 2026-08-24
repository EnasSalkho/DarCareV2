<?php
// app/Modules/Ratings/Services/RatingService.php

namespace App\Modules\Ratings\Services;

use App\Modules\Providers\Models\Provider;
use App\Modules\Ratings\Contracts\RatingServiceInterface;
use App\Modules\Ratings\Models\Rating;
use Illuminate\Support\Facades\DB;

class RatingService implements RatingServiceInterface
{
    public function rateProvider(int $userId, array $data): object
    {
        return DB::transaction(function () use ($userId, $data) {
            $rating = Rating::updateOrCreate(
                ['user_id' => $userId, 'service_request_id' => $data['service_request_id']],
                [
                    'provider_id' => $data['provider_id'],
                    'rating'      => $data['rating'],
                    'comment'     => $data['comment'] ?? null,
                ]
            );

            // Recalculate provider average — cross-module via model directly on providers table
            // Acceptable: only updating a column, not importing business logic
            $avg = Rating::where('provider_id', $data['provider_id'])->avg('rating');
            Provider::where('id', $data['provider_id'])->update(['rating_avg' => round($avg, 2)]);

            return $rating;
        });
    }

    public function getProviderRatings(int $providerId): mixed
    {
        return Rating::where('provider_id', $providerId)
            ->orderByDesc('created_at')
            ->paginate(10);
    }



    public function getAllRatingsForAdmin(?int $ratingValue = null, ?int $providerId = null)
    {
        // جلب التقييم مع بيانات الزبون، الحرفي، وطلب الصيانة المرتبط فيه!
        $query = Rating::with([
            'user:id,name,phone',
            'provider:id,name',
            'serviceRequest' // افترضنا وجود title أو حقول أساسية بالطلب
        ]);

        // تعديل اسم الحقل ليكون rating ليطابق الموديل تبعك
        if ($ratingValue) {
            $query->where('rating', $ratingValue);
        }

        if ($providerId) {
            $query->where('provider_id', $providerId);
        }

        return $query->latest()->paginate(15);
    }

    public function deleteRating(int $id): bool
    {
        $rating = Rating::find($id);
        if (!$rating) {
            return false;
        }
        return $rating->delete();
    }
}

