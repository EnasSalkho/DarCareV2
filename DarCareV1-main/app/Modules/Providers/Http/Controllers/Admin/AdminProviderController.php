<?php

namespace App\Modules\Providers\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Providers\Contracts\ProviderServiceInterface;
use App\Enums\ProviderStatusEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use App\Enums\ProviderVerificationStatusEnum;

class AdminProviderController extends Controller
{
    // حقن خدمة المزودين
    public function __construct(
        private readonly ProviderServiceInterface $providerService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $providers = $this->providerService->getAllProvidersForAdmin(
            $request->query('status')
        );

        return response()->json([
            'status' => 'success',
            'data' => $providers
        ], 200);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        // التحقق من أن الحالة المرسلة تنتمي للـ Enum الخاص بنا حصراً
        $request->validate([
            'status' => ['required', new Enum(ProviderStatusEnum::class)]
        ]);

        $updatedProvider = $this->providerService->updateProviderStatusForAdmin(
            $id,
            $request->status
        );

        return response()->json([
            'status' => 'success',
            'message' => 'تم تحديث حالة المزود بنجاح وفقاً للنظام',
            'data' => $updatedProvider
        ], 200);
    }

    public function updateVerificationStatus(
    Request $request,
    int $id
): JsonResponse {
    $request->validate([
        'verification_status' => [
            'required',
            new Enum(ProviderVerificationStatusEnum::class)
        ],

        'rejection_reason' => [
            'nullable',
            'string',
            'max:1000',
            'required_if:verification_status,rejected',
        ],
    ]);

    $provider = $this->providerService->updateProviderVerificationStatusForAdmin(
        $id,
        $request->verification_status,
        $request->rejection_reason
    );

    return response()->json([
        'status' => 'success',
        'message' => 'تم تحديث حالة التحقق بنجاح',
        'data' => $provider,
    ], 200);
}
}
