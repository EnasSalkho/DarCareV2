<?php
// app/Modules/Favorites/Services/FavoriteService.php

namespace App\Modules\Favorites\Services;

use App\Modules\Favorites\Contracts\FavoriteServiceInterface;
use App\Modules\Favorites\Models\Favorite;
use App\Modules\Providers\Models\Provider;
use Illuminate\Support\Facades\DB;

class FavoriteService implements FavoriteServiceInterface
{
    public function toggle(int $userId, int $providerId): array
    {
        $existing = Favorite::where('user_id', $userId)
            ->where('provider_id', $providerId)
            ->first();

        if ($existing) {
            $existing->delete();
            return ['action' => 'removed', 'is_favorite' => false];
        }

        Favorite::create(['user_id' => $userId, 'provider_id' => $providerId]);
        return ['action' => 'added', 'is_favorite' => true];
    }

    public function list(int $userId): mixed
    {
        return Favorite::where('user_id', $userId)
            ->with([]) // No direct model cross-reference; provider data fetched via ID
            ->paginate(15);
    }


   public function getMostFavoritedProviders()
    {
        // استعلام ذكي يجمع التكرارات من جدول المفضلة ويجلب بيانات الحرفي المرتبط
        return Favorite::select('provider_id', DB::raw('count(*) as total_favorites'))
            ->with(['provider:id,name,phone']) // جلب اسم وهاتف الحرفي المفضل
            ->groupBy('provider_id')
            ->orderBy('total_favorites', 'desc')
            ->take(10) // جلب أفضل 10 حرفيين شعبية بالسيستم
            ->get();
    }
}
