<?php
// app/Modules/Locations/Services/LocationService.php

namespace App\Modules\Locations\Services;

use App\Modules\Locations\Contracts\LocationServiceInterface;
use App\Modules\Locations\Models\Address;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LocationService implements LocationServiceInterface
{
    public function createAddress(string $ownerType, int $ownerId, array $data, bool $isPrimary = false): object
    {
        return DB::transaction(function () use ($ownerType, $ownerId, $data, $isPrimary) {
            if ($isPrimary) {
                Address::where('addressable_type', $ownerType)
                       ->where('addressable_id', $ownerId)
                       ->update(['is_primary' => false]);
            }

            return Address::create([
                'addressable_type' => $ownerType,
                'addressable_id'   => $ownerId,
                'latitude'         => $data['latitude'],
                'longitude'        => $data['longitude'],
                'label'            => $data['label'] ?? 'home',
                'is_primary'       => $isPrimary,
            ]);
        });
    }

    public function setPrimaryAddress(string $ownerType, int $ownerId, int $addressId): object
    {
        return DB::transaction(function () use ($ownerType, $ownerId, $addressId) {
            $address = Address::where('addressable_type', $ownerType)
                              ->where('addressable_id', $ownerId)
                              ->findOrFail($addressId);

            Address::where('addressable_type', $ownerType)
                   ->where('addressable_id', $ownerId)
                   ->update(['is_primary' => false]);

            $address->update(['is_primary' => true]);
            
            return $address->fresh();
        });
    }

    public function deleteAddress(string $ownerType, int $ownerId, int $addressId): void
    {
        $address = Address::where('addressable_type', $ownerType)
                          ->where('addressable_id', $ownerId)
                          ->findOrFail($addressId);

        if ($address->is_primary) {
            throw ValidationException::withMessages([
                'address' => ['Cannot delete the primary address.'],
            ]);
        }

        $address->delete();
    }

    public function getAddresses(string $ownerType, int $ownerId): \Illuminate\Database\Eloquent\Collection
    {
        return Address::where('addressable_type', $ownerType)
                      ->where('addressable_id', $ownerId)
                      ->orderByDesc('is_primary')
                      ->get();
    }

    public function getAllAddressesForAdmin()
    {
        // تم إزالة with('addressable') لأن النماذج الأخرى غير موجودة في هذه القاعدة
        return Address::latest()->paginate(15);
    }

    public function getNearbyOwners(
    float $latitude,
    float $longitude,
    float $radius,
    string $type
): array {
    $locations = Address::query()
        ->where('addressable_type', $type)
        ->select([
            'addressable_id as owner_id',
            'latitude',
            'longitude',
        ])
        ->selectRaw(
            '(
                6371 * acos(
                    cos(radians(?))
                    * cos(radians(latitude))
                    * cos(radians(longitude) - radians(?))
                    + sin(radians(?))
                    * sin(radians(latitude))
                )
            ) AS distance',
            [$latitude, $longitude, $latitude]
        )
        ->having('distance', '<=', $radius)
        ->orderBy('distance')
        ->get();

    // اختيار أقرب عنوان لكل Provider
    $nearestLocations = $locations
        ->groupBy('owner_id')
        ->map(function ($ownerLocations) {
            return $ownerLocations->first();
        })
        ->values();

    return $nearestLocations->toArray();
}

    public function getAddressById(int $addressId): object
    {
        return Address::findOrFail($addressId);
    }
}
