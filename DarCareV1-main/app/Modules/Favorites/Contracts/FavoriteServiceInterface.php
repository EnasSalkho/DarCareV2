<?php
// app/Modules/Favorites/Contracts/FavoriteServiceInterface.php

namespace App\Modules\Favorites\Contracts;

interface FavoriteServiceInterface
{
    public function toggle(int $userId, int $providerId): array;
    public function list(int $userId): mixed;

    //  جلب قائمة بالحرفيين الأكثر إضافة في المفضلة عند الزبائن
    public function getMostFavoritedProviders();
}
