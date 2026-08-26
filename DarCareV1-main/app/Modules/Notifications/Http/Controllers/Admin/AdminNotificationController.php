<?php

namespace App\Modules\Notifications\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Contracts\NotificationServiceInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminNotificationController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly NotificationServiceInterface $notificationService
    ) {}

    public function send(Request $request): JsonResponse
    {
        $recipientType = $request->string('recipient_type')->toString() ?: 'user';

        $request->validate([
            'target' => 'required|in:all,users,providers,specific',
            'recipient_type' => 'nullable|in:user,provider',
            'user_id' => [
                'required_if:target,specific',
                'integer',
                // مقدّم الخدمة موجود بجدول providers مو بجدول users
                Rule::exists($recipientType === 'provider' ? 'providers' : 'users', 'id'),
            ],
            'title' => 'required|string|max:150',
            'message' => 'required|string',
        ]);

        $result = $this->notificationService->sendBulkNotification(
            $request->string('target')->toString(),
            $request->string('title')->toString(),
            $request->string('message')->toString(),
            $request->integer('user_id') ?: null,
            $recipientType
        );

        if (! ($result['success'] ?? false)) {
            $message = (string) ($result['message'] ?? '');
            $status = (str_contains($message, 'Too many recipients') || str_contains($message, 'No recipients'))
                ? 422
                : 503;

            return $this->error($result['message'] ?? 'Bulk notification failed.', [
                'recipients' => $result['recipients'] ?? null,
            ], $status);
        }

        return $this->success([
            'recipients' => $result['recipients'] ?? 0,
            'database_stored' => $result['database_stored'] ?? 0,
            'devices_attempted' => $result['devices_attempted'] ?? 0,
            'sent' => $result['sent'] ?? 0,
            'failed' => $result['failed'] ?? 0,
            'invalidated' => $result['invalidated'] ?? 0,
        ], $result['message'] ?? 'Bulk notification processing completed.');
    }

    public function getUsersList(): JsonResponse
    {
        $users = \App\Modules\Users\Models\User::query()
            ->where('role', '!=', 'admin')
            ->get(['id', 'name'])
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'recipient_type' => 'user',
            ]);

        $providers = \App\Modules\Providers\Models\Provider::query()
            ->get(['id', 'name'])
            ->map(fn ($provider) => [
                'id' => $provider->id,
                'name' => $provider->name,
                'recipient_type' => 'provider',
            ]);

        return $this->success(
            $users->concat($providers)->values(),
            'Users list retrieved successfully'
        );
    }
}
