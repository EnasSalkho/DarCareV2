<?php

namespace App\Modules\ServiceRequests\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\ServiceRequests\Contracts\ServiceRequestServiceInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminServiceRequestController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly ServiceRequestServiceInterface $serviceRequestService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $requests = $this->serviceRequestService->getAllRequestsForAdmin(
            $request->query('status'),
            $request->query('urgency')
        );

        return $this->success($requests);
    }

    public function show(int $id): JsonResponse
    {
        $requestDetails = $this->serviceRequestService->getRequestDetailsForAdmin($id);

        return $this->success($requestDetails);
    }

    public function reassign(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'provider_id' => ['required', 'integer', 'exists:providers,id'],
        ]);

        $serviceRequest = $this->serviceRequestService->reassignProvider(
            $id,
            (int) $validated['provider_id']
        );

        return $this->success($serviceRequest, 'Provider reassigned successfully');
    }
}
