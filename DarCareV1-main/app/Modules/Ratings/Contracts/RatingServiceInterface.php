<?php
// app/Modules/Ratings/Contracts/RatingServiceInterface.php

namespace App\Modules\Ratings\Contracts;

interface RatingServiceInterface
{
    public function rateProvider(int $userId, array $data): object;
    public function getProviderRatings(int $providerId): mixed;

    public function getAllRatingsForAdmin(?int $stars, ?int $providerId);
    public function deleteRating(int $id): bool;

}
