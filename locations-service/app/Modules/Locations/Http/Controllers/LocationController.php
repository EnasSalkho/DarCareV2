<?php
namespace App\Modules\Locations\Http\Controllers;

use App\Modules\Locations\Http\Requests\StoreAddressRequest;
use App\Modules\Locations\Http\Resources\AddressResource;
use App\Modules\Locations\Services\LocationService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LocationController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly LocationService $locationService) {}

    /**
     * دالة مساعدة لتحويل النص المختصر إلى المسار الكامل للمودل
     */
    private function resolveOwnerType(string $type): string
    {
        return strtolower($type);
    }

    public function show(int $addressId): JsonResponse
    {
        $address = $this->locationService->getAddressById($addressId);

        return response()->json([
            'status' => 'success',
            'data' => new AddressResource($address)
        ]);
    }
    
    public function nearbyOwners(Request $request)
{
    $request->validate([
        'latitude'  => 'required|numeric',
        'longitude' => 'required|numeric',
        'radius'    => 'nullable|numeric',
        'type'      => 'required|string',
    ]);

    $nearbyOwners = $this->locationService->getNearbyOwners(
        (float) $request->latitude,
        (float) $request->longitude,
        (float) ($request->radius ?? 10),
        $request->type
    );

    return response()->json([
        'status' => 'success',
        'data' => $nearbyOwners,
    ]);
}

        public function index(string $ownerType, int $ownerId): \Illuminate\Http\JsonResponse
    {
        $realType = $this->resolveOwnerType($ownerType);

        $addresses = $this->locationService->getAddresses($realType, $ownerId);
            
        return response()->json([
            'status' => 'success',
            'data' => AddressResource::collection($addresses)
        ]);
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        $realType = $this->resolveOwnerType($request->addressable_type);

        $address = $this->locationService->createAddress(
            $realType,
            $request->addressable_id,
            $request->validated()
        );
        
        return response()->json([
            'status' => 'success',
            'data' => new AddressResource($address)
        ], 201);
    }

    public function setPrimary(string $ownerType, int $ownerId, int $addressId): JsonResponse
    {
        $realType = $this->resolveOwnerType($ownerType);

        $address = $this->locationService->setPrimaryAddress($realType, $ownerId, $addressId);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Primary address updated',
            'data' => new AddressResource($address)
        ]);
    }

    public function destroy(string $ownerType, int $ownerId, int $addressId): JsonResponse
    {
        $realType = $this->resolveOwnerType($ownerType);

        $this->locationService->deleteAddress($realType, $ownerId, $addressId);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Address deleted'
        ]);
    }
}