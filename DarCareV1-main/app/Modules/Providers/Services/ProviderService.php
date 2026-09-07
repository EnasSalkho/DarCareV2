<?php
// app/Modules/Providers/Services/ProviderService.php

namespace App\Modules\Providers\Services;

use App\Modules\Providers\Contracts\ProviderServiceInterface;
use App\Modules\Providers\Models\Provider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Enums\ProviderStatusEnum;
use App\Mail\ProviderApprovedMail;
use App\Mail\ProviderRejectedMail;
use App\Services\LocationClient;

class ProviderService implements ProviderServiceInterface
{
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
        $query = Provider::with('categories')->visibleToClients();

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
            ->visibleToClients()
            ->paginate(15);
    }

    // ✅ تم التعديل هنا للتعامل مع LocationService والفلترة حسب الفئة
    public function getNearbyProviders(float $latitude, float $longitude, float $radius, ?int $categoryId = null): mixed
    {
        // 1. إرسال طلب HTTP (مرة واحدة فقط) للمايكروسيرفس الخاص بالمواقع
        // يفترض أن يرجع هذا الطلب مصفوفة تحتوي على (owner_id, latitude, longitude)
        $nearbyData = $this->locationClient->getNearbyOwners($latitude, $longitude, $radius, 'provider');

        // إذا كان الرد فارغاً أو فشل الاتصال
        if (empty($nearbyData) || !is_array($nearbyData)) {
            return collect([]);
        }

        // تحويل البيانات إلى Collection لتسهيل التعامل معها
        $locationsCollection = collect($nearbyData);
        
        // استخراج أرقام المزودين (IDs) فقط لعمل الاستعلام
        $providerIds = $locationsCollection->pluck('owner_id')->toArray();

        // 2. الاستعلام من قاعدة البيانات المحلية لمزودي الخدمات
        $query = Provider::with('categories')
            ->visibleToClients()
            ->whereIn('id', $providerIds);

        if ($categoryId) {
            $query->whereHas('categories', function ($q) use ($categoryId) {
                $q->where('categories.id', $categoryId);
            });
        }

        $providers = $query->get();

        // 3. دمج الإحداثيات (بدون أي طلب HTTP داخل اللوب!)
        return $providers->map(function ($provider) use ($locationsCollection) {
            // البحث عن إحداثيات المزود من البيانات التي جلبناها مسبقاً
            $locationInfo = $locationsCollection->firstWhere('owner_id', $provider->id);

            $provider->latitude = $locationInfo['latitude'] ?? null;
            $provider->longitude = $locationInfo['longitude'] ?? null;
            
            // حساب المسافة (اختياري، إذا كان الـ Location Microservice يرجعها)
            $provider->distance = $locationInfo['distance'] ?? null; 

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

    /**
     * Soft-deletes the provider so past service requests and ratings keep
     * resolving, and revokes their tokens so an open session cannot keep
     * using the API after removal.
     */
    public function deleteProviderForAdmin(int $providerId): void
    {
        $provider = Provider::findOrFail($providerId);

        DB::transaction(function () use ($provider) {
            $provider->tokens()->delete();
            $provider->delete();
        });
    }

    public function getProvidersByCategory(int $categoryId): mixed
    {
        return Provider::with('categories')
            ->visibleToClients()
            ->whereHas('categories', function ($query) use ($categoryId) {
                $query->where('categories.id', $categoryId);
            })
            ->paginate(15);
    }
    public function updateProviderVerificationStatusForAdmin(
    int $providerId,
    string $verificationStatus,
    ?string $rejectionReason = null
): object {
    $provider = Provider::findOrFail($providerId);

    $previousStatus = $provider->verification_status?->value;

    $provider->update([
        'verification_status' => $verificationStatus,
        'rejection_reason' => $verificationStatus === 'rejected'
            ? $rejectionReason
            : null,
    ]);

    $provider = $provider->fresh();

    // Only on a real transition, so re-saving the same decision does not spam
    // the provider with a duplicate email.
    if ($verificationStatus !== $previousStatus) {
        if ($verificationStatus === 'approved') {
            $this->sendVerificationMail($provider, new ProviderApprovedMail($provider), 'approval');
        } elseif ($verificationStatus === 'rejected') {
            $this->sendVerificationMail($provider, new ProviderRejectedMail($provider), 'rejection');
        }
    }

    return $provider;
}

    /**
     * A failed mail delivery must not roll back the decision itself, so the
     * error is logged and swallowed.
     */
    private function sendVerificationMail(
        Provider $provider,
        \Illuminate\Mail\Mailable $mailable,
        string $kind
    ): void {
        if (! filter_var($provider->email, FILTER_VALIDATE_EMAIL)) {
            Log::warning("Skipped {$kind} mail: invalid provider email", [
                'provider_id' => $provider->id,
            ]);

            return;
        }

        try {
            Mail::to($provider->email)->send($mailable);
        } catch (\Throwable $e) {
            Log::error("Failed to send provider {$kind} mail", [
                'provider_id' => $provider->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}