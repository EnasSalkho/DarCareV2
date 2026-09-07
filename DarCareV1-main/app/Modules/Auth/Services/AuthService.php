<?php
// app/Modules/Auth/Services/AuthService.php

namespace App\Modules\Auth\Services;

use App\Exceptions\ProviderNotVerifiedException;
use App\Modules\Auth\Contracts\AuthServiceInterface;
use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Auth\Http\Requests\RegisterProviderRequest;
use App\Modules\Auth\Http\Requests\RegisterUserRequest;
use App\Modules\Notifications\Services\DeviceTokenService;
use App\Modules\Users\Models\User;
use App\Modules\Providers\Models\Provider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthService implements AuthServiceInterface
{
    public function __construct(
        // قمنا بإزالة LocationServiceInterface لأننا سنعتمد على الـ API
        private readonly DeviceTokenService $deviceTokenService
    ) {}

    public function registerUser(RegisterUserRequest $request): array
    {
        $imagePath = null;
        if ($request->hasFile('profile_image')) {
            $imagePath = $request->file('profile_image')->store('users/images', 'public');
        }

        $user = User::create([
            'name'          => $request->name,
            'phone'         => $request->phone,
            'email'         => $request->email,
            'password'      => Hash::make($request->password),
            'city_id'       => $request->city_id,
            'role'          => 'user',
            'profile_image' => $imagePath,
        ]);

        // استدعاء الدالة الخاصة بإرسال الـ API Request
        $this->createAddressViaApi($user, $request->address, $request->city_id, true);

        $token = $user->createToken('auth_token')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    public function registerProvider(RegisterProviderRequest $request): array
    {
        $profileImagePath = $request->file('profile_image')
    ->store('providers/images', 'public');

$identityImagePath = $request->file('identity_image')
    ->store('providers/identity', 'public');

        $provider = Provider::create([
    'name'                => $request->name,
    'phone'               => $request->phone,
    'email'               => $request->email,
    'password'            => Hash::make($request->password),
    'years_of_experience' => $request->years_of_experience,
    'bio'                 => $request->bio,

    'profile_image'      => $profileImagePath,
    'identity_image'     => $identityImagePath,

    'verification_status' => 'pending',
    'status'              => 'available',
]);

        $provider->categories()->sync($request->category_ids);

        // استدعاء الدالة الخاصة بإرسال الـ API Request
        // مزود الخدمة قد لا يملك city_id في الـ Request الحالي، لذا نمرر null
        $this->createAddressViaApi($provider, $request->address, null, true);

        // جلب الـ address من Location Service
        $locationServiceUrl = env(
            'LOCATION_SERVICE_URL',
            'http://127.0.0.1:8001'
        );

        $addressResponse = Http::timeout(5)->get(
            $locationServiceUrl . '/api/v1/addresses/provider/' . $provider->id
        );

        $address = [];

        if ($addressResponse->successful()) {
            $address = $addressResponse->json('data.0', []);
        }

        $token = $provider->createToken('auth_token')->plainTextToken;

        $provider->latitude = $address['latitude'] ?? null;
        $provider->longitude = $address['longitude'] ?? null;

        return [
            'provider' => $provider,
            'token' => $token,
        ];
    }

    public function login(LoginRequest $request, string $role): array
    {
        $model = match ($role) {
            'user'     => User::where('email', $request->email)->first(),
            'provider' => Provider::where('email', $request->email)->first(),
        };

        if (!$model || !Hash::check($request->password, $model->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // A provider whose account is still pending (or was rejected) must not
        // receive a token. Checked after the password so the response never
        // reveals whether an email exists.
        if ($role === 'provider') {
            $verificationStatus = $model->verification_status instanceof \BackedEnum
                ? $model->verification_status->value
                : (string) $model->verification_status;

            if ($verificationStatus !== 'approved') {
                throw new ProviderNotVerifiedException(
                    $verificationStatus,
                    $model->rejection_reason
                );
            }
        }

        // جلب الـ address من Location Service
        $locationServiceUrl = env(
            'LOCATION_SERVICE_URL',
            'http://127.0.0.1:8001'
        );

        $addressResponse = Http::timeout(5)->get(
            $locationServiceUrl . '/api/v1/addresses/' .
            $role . '/' . $model->id
        );

        $address = [];

        if ($addressResponse->successful()) {
            $address = $addressResponse->json('data.0', []);
        }

        // إضافة الإحداثيات مؤقتًا للـ model
        $model->latitude = $address['latitude'] ?? null;
        $model->longitude = $address['longitude'] ?? null;

        $token = $model->createToken('auth_token')->plainTextToken;

        return [
            'user' => $model,
            'token' => $token
        ];
    }

    public function logout($user, ?string $deviceToken = null): void
    {
        if (is_string($deviceToken) && $deviceToken !== '') {
            $this->deviceTokenService->invalidateToken($deviceToken);
        }

        $user->currentAccessToken()->delete();
    }

    /**
     * إرسال طلب HTTP إلى خدمة المواقع لإنشاء عنوان جديد
     */
    private function createAddressViaApi(
    Model $model,
    array $addressData,
    ?int $cityId = null,
    bool $isPrimary = true
    ): void {
        $locationServiceUrl =
            env('LOCATION_SERVICE_URL', 'http://127.0.0.1:8001')
            . '/api/v1/addresses';

        $payload = [
            'addressable_id'   => $model->id,
            'addressable_type' => $model->getMorphClass(),
            'latitude'         => $addressData['latitude'],
            'longitude'        => $addressData['longitude'],
            'label'            => $addressData['label'] ?? 'home',
            'is_primary'       => $isPrimary,
        ];

        if ($cityId) {
            $payload['city_id'] = $cityId;
        }

        try {
            $response = Http::timeout(5)->post($locationServiceUrl, $payload);

            if ($response->failed()) {
                Log::error('Failed to create address in Location Service', [
                    'url'      => $locationServiceUrl,
                    'model_id' => $model->id,
                    'type'     => $model->getMorphClass(),
                    'status'   => $response->status(),
                    'response' => $response->json(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Location Service is Unreachable', [
                'url'   => $locationServiceUrl,
                'error' => $e->getMessage()
            ]);
        }
    }
}