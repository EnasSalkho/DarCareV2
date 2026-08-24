<?php

namespace App\Modules\Locations\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Locations\Contracts\LocationServiceInterface;
use Illuminate\Http\JsonResponse;

class AdminLocationController extends Controller
{
    public function __construct(
        private readonly LocationServiceInterface $locationService
    ) {}

    /**
     * عرض كافة عناوين السيستم للأدمن
     */
    public function index(): JsonResponse
    {
        $addresses = $this->locationService->getAllAddressesForAdmin();

        return response()->json([
            'status' => 'success',
            'data' => $addresses
        ], 200);
    }
}
