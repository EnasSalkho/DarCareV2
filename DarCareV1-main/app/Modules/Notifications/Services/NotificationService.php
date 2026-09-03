<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Contracts\NotificationServiceInterface;
use App\Modules\Providers\Models\Provider;
use App\Modules\Users\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NotificationService implements NotificationServiceInterface
{
    public const BULK_RECIPIENT_LIMIT = 100;

    public function __construct(
        private readonly DeviceTokenService $deviceTokenService
    ) {}

    public function hasFirebaseCredentials(): bool
    {
        $path = $this->credentialsPath();
        $projectId = config('services.firebase.project_id');

        return is_string($path)
            && $path !== ''
            && is_file($path)
            && is_string($projectId)
            && $projectId !== '';
    }

    public function storeDatabaseNotification(object $notifiable, string $type, array $data, array $extra = []): object
    {
        $payload = array_merge($data, ['type' => $data['type'] ?? $type]);

        return $notifiable->notifications()->create(array_merge([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'data' => $payload,
        ], $extra));
    }

    public function sendToUser(int $userId, string $type, array $data): void
    {
        $user = User::query()->find($userId);

        if (! $user) {
            return;
        }

        $this->storeDatabaseNotification($user, $type, $data);
        $this->sendPushImmediately($user, $type, $data);
    }

    public function sendToProvider(int $providerId, string $type, array $data): void
    {
        $provider = Provider::query()->find($providerId);

        if (! $provider) {
            return;
        }

        $this->storeDatabaseNotification($provider, $type, $data);
        $this->sendPushImmediately($provider, $type, $data);
    }

    public function sendBulkNotification(
        string $target,
        string $title,
        string $message,
        ?int $recipientId = null,
        string $recipientType = 'user'
    ): array {
        if (! $this->hasFirebaseCredentials()) {
            Log::warning('Bulk notification aborted: Firebase credentials are missing.', [
                'service' => 'firebase',
            ]);

            return [
                'success' => false,
                'message' => 'Firebase credentials are not configured. Bulk notification was not sent.',
            ];
        }

        $recipients = $this->resolveBulkRecipients($target, $recipientId, $recipientType);
        $recipientCount = $recipients->count();

        if ($recipientCount === 0) {
            return [
                'success' => false,
                'message' => 'No recipients matched the selected target.',
                'recipients' => 0,
            ];
        }

        if ($recipientCount > self::BULK_RECIPIENT_LIMIT) {
            return [
                'success' => false,
                'message' => 'Too many recipients for a synchronous bulk send. Maximum is '
                    .self::BULK_RECIPIENT_LIMIT.'. Selected: '.$recipientCount.'.',
                'recipients' => $recipientCount,
            ];
        }

        $type = ($target === 'specific') ? 'admin_direct' : 'admin_bulk';

        // كل مستلم بياخد سطر (مشان يقدر يقرأ/يحذف إشعاره)، بس كلهم تحت batch واحد
        // مشان الأدمن يشوف الرسالة مرة وحدة بالسجل.
        $batch = [
            'batch_id' => (string) Str::uuid(),
            'audience' => $target,
        ];

        $data = [
            'title' => $title,
            'body' => $message,
            'message' => $message,
            'route' => 'notifications',
        ];

        $databaseStored = 0;
        $devicesAttempted = 0;
        $sent = 0;
        $failed = 0;
        $invalidated = 0;

        foreach ($recipients as $recipient) {
            $this->storeDatabaseNotification($recipient, $type, $data, $batch);
            $databaseStored++;

            $push = $this->sendPushImmediately($recipient, $type, $data);
            $devicesAttempted += $push['attempted'];
            $sent += $push['sent'];
            $failed += $push['failed'];
            $invalidated += $push['invalidated'];
        }

        return [
            'success' => true,
            'message' => 'Bulk notification processing completed.',
            'batch_id' => $batch['batch_id'],
            'recipients' => $recipientCount,
            'database_stored' => $databaseStored,
            'devices_attempted' => $devicesAttempted,
            'sent' => $sent,
            'failed' => $failed,
            'invalidated' => $invalidated,
        ];
    }

    /**
     * المستخدمون ومقدمو الخدمة في جدولين مختلفين، فلازم كل هدف ينحل على الموديل الصحيح.
     *
     * @return Collection<int, object>
     */
    private function resolveBulkRecipients(string $target, ?int $recipientId, string $recipientType): Collection
    {
        return match ($target) {
            'specific' => $this->findSpecificRecipient($recipientId, $recipientType),
            'users' => collect(User::query()->where('role', 'user')->get()->all()),
            'providers' => collect(Provider::query()->get()->all()),
            // "all" targets customers + providers only (not admins).
            default => collect(User::query()->where('role', '!=', 'admin')->get()->all())
                ->merge(Provider::query()->get()->all()),
        };
    }

    /**
     * @return Collection<int, object>
     */
    private function findSpecificRecipient(?int $recipientId, string $recipientType): Collection
    {
        if (! $recipientId) {
            return collect();
        }

        $recipient = $recipientType === 'provider'
            ? Provider::query()->find($recipientId)
            : User::query()->find($recipientId);

        return $recipient ? collect([$recipient]) : collect();
    }

    public function sendFcmToTokens(array $tokens, string $title, string $body, array $data = []): array
    {
        $uniqueTokens = array_values(array_unique(array_filter($tokens)));
        $result = [
            'attempted' => count($uniqueTokens),
            'sent' => 0,
            'failed' => 0,
            'invalidated' => 0,
        ];

        if ($uniqueTokens === []) {
            return $result;
        }

        if (! $this->hasFirebaseCredentials()) {
            Log::warning('FCM send skipped: Firebase credentials are missing.', [
                'service' => 'firebase',
            ]);
            $result['failed'] = $result['attempted'];

            return $result;
        }

        $accessToken = $this->getGoogleAccessToken();

        if (! $accessToken) {
            Log::warning('FCM send aborted: unable to obtain Google access token.', [
                'service' => 'firebase',
            ]);
            $result['failed'] = $result['attempted'];

            return $result;
        }

        $projectId = config('services.firebase.project_id');
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $stringData = [];
        foreach ($data as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $stringData[(string) $key] = (string) ($value ?? '');
            }
        }

        foreach ($uniqueTokens as $token) {
            try {
                $response = Http::withToken($accessToken)->post($url, [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => array_merge([
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        ], $stringData),
                    ],
                ]);

                if ($response->successful()) {
                    $result['sent']++;
                    continue;
                }

                $result['failed']++;
                $errorCode = data_get($response->json(), 'error.details.0.errorCode')
                    ?? data_get($response->json(), 'error.status');

                Log::warning('FCM delivery failed', [
                    'service' => 'firebase',
                    'token' => $this->maskToken($token),
                    'status' => $response->status(),
                    'error' => $errorCode,
                ]);

                if ($this->isInvalidTokenError($response->json(), $errorCode)) {
                    $this->deviceTokenService->invalidateToken($token);
                    $result['invalidated']++;
                }
            } catch (\Throwable $e) {
                $result['failed']++;
                Log::warning('FCM delivery exception', [
                    'service' => 'firebase',
                    'token' => $this->maskToken($token),
                    'exception' => $e::class,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    public function sendPushImmediately(object $notifiable, string $type, array $data): array
    {
        $title = (string) ($data['title'] ?? 'Notification');
        $body = (string) ($data['body'] ?? $data['message'] ?? '');
        $tokens = $this->resolveTokensForNotifiable($notifiable);

        if ($tokens === []) {
            return [
                'attempted' => 0,
                'sent' => 0,
                'failed' => 0,
                'invalidated' => 0,
            ];
        }

        $payload = array_merge($data, ['type' => $data['type'] ?? $type]);

        return $this->sendFcmToTokens($tokens, $title, $body, $payload);
    }

    /**
     * @return list<string>
     */
    private function resolveTokensForNotifiable(object $notifiable): array
    {
        $tokens = [];

        if (method_exists($notifiable, 'deviceTokens')) {
            $tokens = $notifiable->deviceTokens()
                ->valid()
                ->pluck('token')
                ->filter()
                ->values()
                ->all();
        }

        if ($tokens === [] && ! empty($notifiable->fcm_token)) {
            $tokens = [(string) $notifiable->fcm_token];
        }

        return $tokens;
    }

    private function getGoogleAccessToken(): ?string
    {
        $path = $this->credentialsPath();

        if (! $path || ! is_file($path)) {
            Log::warning('Firebase credentials file not found.', [
                'service' => 'firebase',
                'path' => $path,
            ]);

            return null;
        }

        $credentials = json_decode((string) file_get_contents($path), true);

        if (! is_array($credentials) || empty($credentials['private_key']) || empty($credentials['client_email'])) {
            Log::warning('Firebase credentials file is invalid.', [
                'service' => 'firebase',
            ]);

            return null;
        }

        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $now = time();
        $payload = $this->base64UrlEncode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now,
        ]));

        $signature = '';
        $signed = openssl_sign(
            $header.'.'.$payload,
            $signature,
            $credentials['private_key'],
            'SHA256'
        );

        if (! $signed) {
            Log::warning('Failed to sign Firebase JWT.', [
                'service' => 'firebase',
            ]);

            return null;
        }

        $jwt = $header.'.'.$payload.'.'.$this->base64UrlEncode($signature);

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if (! $response->successful()) {
            Log::warning('Failed to exchange Firebase JWT for access token.', [
                'service' => 'firebase',
                'status' => $response->status(),
            ]);

            return null;
        }

        return $response->json('access_token');
    }

    private function credentialsPath(): ?string
    {
        $configured = config('services.firebase.credentials');

        if (! is_string($configured) || $configured === '') {
            return null;
        }

        if (is_file($configured)) {
            return $configured;
        }

        $relative = base_path($configured);

        return is_file($relative) ? $relative : null;
    }

    private function maskToken(string $token): string
    {
        $suffix = substr($token, -6);

        return '***'.$suffix;
    }

    private function isInvalidTokenError(mixed $json, mixed $errorCode): bool
    {
        $haystack = strtoupper(json_encode($json) ?: '');

        if (in_array(strtoupper((string) $errorCode), ['UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND'], true)) {
            return true;
        }

        return str_contains($haystack, 'UNREGISTERED')
            || str_contains($haystack, 'INVALID_ARGUMENT')
            || str_contains($haystack, 'INVALID');
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
