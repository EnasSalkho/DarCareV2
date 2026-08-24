<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class LocationClient
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.location.url');
    }

    public function getNearbyOwners(
        float $latitude,
        float $longitude,
        float $radius,
        string $type
    ): array {
        $response = Http::timeout(10)->get(
            $this->baseUrl . '/api/v1/addresses/nearby-owners',
            [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'radius' => $radius,
                'type' => $type,
            ]
        );

        if ($response->failed()) {
            throw new \Exception(
                'Location service error: ' . $response->body()
            );
        }

        return $response->json('data.ids', []);
    }

    public function getProviderLocation(int $providerId): array
    {
        $response = Http::timeout(10)->get(
            $this->baseUrl . '/api/v1/addresses/provider/' . $providerId
        );

        if ($response->failed()) {
            return [];
        }

        return $response->json('data.0', []);
    }
}