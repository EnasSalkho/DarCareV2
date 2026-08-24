<?php
// app/Modules/Providers/Contracts/ProviderServiceInterface.php

namespace App\Modules\Providers\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface ProviderServiceInterface
{
    public function getProfile(int $providerId): object;
    public function updateProfile(int $providerId, array $data): object;
    public function toggleStatus(int $providerId): object;
    public function searchProviders(array $filters): mixed;
    public function getNearbyProviders(float $latitude, float $longitude, float $radius): mixed;
    public function all();
        //? Admin
    public function getAllProvidersForAdmin(?string $status): mixed;
    public function updateProviderStatusForAdmin(int $providerId, string $status): object;
    public function getProvidersByCategory(int $categoryId): mixed;
}
