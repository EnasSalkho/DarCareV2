<?php
// app/Modules/Providers/Http/Controllers/ProviderController.php

namespace App\Modules\Providers\Http\Controllers;

use App\Modules\Providers\Contracts\ProviderServiceInterface;
use App\Modules\Providers\Http\Requests\UpdateProviderRequest;
use App\Modules\Providers\Http\Resources\ProviderResource;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ProviderController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly ProviderServiceInterface $providerService) {}

    

    public function index(): JsonResponse
    {
        return $this->success(
            ProviderResource::collection(
                $this->providerService->all()
            )
        );
    }
    
    public function profile(Request $request): JsonResponse
    {
        $provider = $this->providerService->getProfile($request->user()->id);
        return $this->success(new ProviderResource($provider));
    }

    public function update(UpdateProviderRequest $request): JsonResponse
    {
        $data = $request->safe()->except('profile_image');

        if ($request->hasFile('profile_image')) {
            $data['profile_image'] = $request->file('profile_image')
                ->store('providers/images', 'public');
        }

        $provider = $this->providerService->updateProfile($request->user()->id, $data);
        return $this->success(new ProviderResource($provider), 'Profile updated');
    }

    /**
     * تبديل حالة استقبال الطلبات.
     *
     * يقبل status صراحةً (available|busy) ليطابق مفتاح التبديل في التطبيق،
     * وبدونه يقلب الحالة الحالية. suspended غير مقبول هنا: إيقاف الحساب قرار
     * إداري يتم من الداشبورد وحده.
     */
    public function toggleStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:available,busy'],
        ]);

        $provider = $this->providerService->toggleStatus(
            $request->user()->id,
            $validated['status'] ?? null
        );

        $status = $provider->status instanceof \BackedEnum
            ? $provider->status->value
            : (string) $provider->status;

        return $this->success([
            'status' => $status,
            // يريح التطبيق من تكرار مقارنة النصوص عند رسم المفتاح.
            'is_available' => $status === \App\Enums\ProviderStatusEnum::Available->value,
        ], 'Status updated');
    }

    public function show(int $id): JsonResponse
    {
        $provider = $this->providerService->getProfile($id);
        return $this->success(new ProviderResource($provider));
    }

    public function search(Request $request): JsonResponse
    {
        $providers = $this->providerService->searchProviders($request->only(['name', 'category_id']));
        return $this->success(ProviderResource::collection($providers));
    }

    // app/Modules/Providers/Http/Controllers/ProviderController.php

public function nearby(\Illuminate\Http\Request $request)
{
    // التحقق من المدخلات
    $request->validate([
        'latitude'    => 'required|numeric',
        'longitude'   => 'required|numeric',
        'radius'      => 'nullable|numeric',
        'category_id' => 'nullable|integer' // 💡 جعلناه اختياري ليعمل في كل الحالات
    ]);

    $radius = $request->input('radius', 10); // مسافة افتراضية 10 كيلو لو لم يرسلها المستخدم
    $categoryId = $request->input('category_id');

    // استدعاء الخدمة
    $providers = $this->providerService->getNearbyProviders(
        $request->latitude,
        $request->longitude,
        $radius,
        $categoryId
    );

    return response()->json([
        'status' => 'success',
        'data' => $providers
    ]);
}
    public function byCategory(int $categoryId): JsonResponse
    {
        $providers = $this->providerService->getProvidersByCategory($categoryId);

        return $this->success(
            ProviderResource::collection($providers)
        );
    }
}
