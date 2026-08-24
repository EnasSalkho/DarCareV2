<?php
// app/Modules/Locations/Contracts/LocationServiceInterface.php

namespace App\Modules\Locations\Contracts;

interface LocationServiceInterface
{
    public function createAddress(string $ownerType, int $ownerId, array $data, bool $isPrimary = false): object;

    // تأكد أيضاً من تطابق باقي الدوال إذا كنت قد غيرتها في الـ Service
    public function setPrimaryAddress(string $ownerType, int $ownerId, int $addressId): object;
    
    public function deleteAddress(string $ownerType, int $ownerId, int $addressId): void;
    
    public function getAddresses(string $ownerType, int $ownerId): \Illuminate\Database\Eloquent\Collection;
    
    public function getAllAddressesForAdmin();
}
