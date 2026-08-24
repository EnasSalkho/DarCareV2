<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Notifications\Services\DeviceTokenService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DeviceTokenController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly DeviceTokenService $deviceTokenService
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'platform' => ['required', 'string', 'in:android,ios,web'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'device_identifier' => ['nullable', 'string', 'max:255'],
        ]);

        $deviceToken = $this->deviceTokenService->register(
            $request->user(),
            $validated['token'],
            $validated['platform'],
            $validated['device_name'] ?? null,
            $validated['device_identifier'] ?? null
        );

        return $this->success([
            'id' => $deviceToken->id,
            'platform' => $deviceToken->platform,
            'device_name' => $deviceToken->device_name,
            'last_used_at' => $deviceToken->last_used_at,
        ], 'Device token registered');
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
        ]);

        $removed = $this->deviceTokenService->remove($request->user(), $validated['token']);

        if (! $removed) {
            return $this->error('Device token not found.', null, 404);
        }

        return $this->success(null, 'Device token removed');
    }
}
