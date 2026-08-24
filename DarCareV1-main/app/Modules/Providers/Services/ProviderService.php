<?php
// app/Modules/Providers/Services/ProviderService.php

namespace App\Modules\Providers\Services;

use App\Modules\Providers\Contracts\ProviderServiceInterface;
use App\Modules\Providers\Models\Provider;
use Illuminate\Support\Facades\DB;
use App\Enums\ProviderStatusEnum;
use App\Services\LocationClient;
// use App\Modules\Locations\Services\LocationService; // 1. استدعاء خدمة المواقع

class ProviderService implements ProviderServiceInterface
{
    //protected LocationService $locationService;

    // 2. حقن LocationService عن طريق الـ Constructor
    // public function __construct(LocationService $locationService)
    // {
    //     $this->locationService = $locationService;
    // }
    protected LocationClient $locationClient;

    public function __construct(LocationClient $locationClient)
    {
        $this->locationClient = $locationClient;
    }

    public function getProfile(int $providerId): object
    {
        return Provider::with('categories')->findOrFail($providerId);
    }

    public function updateProfile(int $providerId, array $data): object
    {
        $provider = Provider::findOrFail($providerId);
        $provider->update($data);
        return $provider->fresh(['categories']);
    }

    public function toggleStatus(int $providerId): object
    {
        $provider = Provider::findOrFail($providerId);
        $newStatus = $provider->status === 'available' ? 'busy' : 'available';
        $provider->update(['status' => $newStatus]);
        return $provider->fresh();
    }

    public function searchProviders(array $filters): mixed
    {
        $query = Provider::with('categories')
            ->where('status', 'available');

        if (!empty($filters['name'])) {
            $query->where('name', 'like', "%{$filters['name']}%");
        }

        if (!empty($filters['category_id'])) {
            $query->whereHas('categories', fn($q) =>
                $q->where('categories.id', $filters['category_id'])
            );
        }

        return $query->paginate(15);
    }

    public function all()
    {
        return Provider::with('categories')
            ->where('status', 'available')
            ->paginate(15);
    }

    public function getNearbyProviders(float $latitude, float $longitude, float $radius): mixed
    {
        $providerIds = $this->locationClient->getNearbyOwners(
            $latitude,
            $longitude,
            $radius,
            'provider'
        );

        if (empty($providerIds)) {
            return [];
        }

        $providers = Provider::whereIn('id', $providerIds)->get();

        return $providers->map(function ($provider) {

            $location = $this->locationClient->getProviderLocation($provider->id);

            $provider->latitude = $location['latitude'] ?? null;
            $provider->longitude = $location['longitude'] ?? null;

            return $provider;
        });
    }

        //? Admin
        public function getAllProvidersForAdmin(?string $status): mixed
        {
            $query = Provider::with('categories:id,name');

            if ($status) {
                $query->where('status', $status);
            }

            return $query->orderBy('rating_avg', 'desc')->paginate(15);
        }

        public function updateProviderStatusForAdmin(int $providerId, string $status): object
        {
            $provider = Provider::findOrFail($providerId);

            $provider->update([
                'status' => $status
            ]);

            return $provider->fresh();
        }
        public function getProvidersByCategory(int $categoryId): mixed
    {
        return Provider::with('categories')
            ->where('status', 'available')
            ->whereHas('categories', function ($query) use ($categoryId) {
                $query->where('categories.id', $categoryId);
            })
            ->paginate(15);
    }
}