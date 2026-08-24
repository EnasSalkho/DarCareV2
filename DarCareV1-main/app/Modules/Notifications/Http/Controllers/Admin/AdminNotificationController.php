<?php

namespace App\Modules\Notifications\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Contracts\NotificationServiceInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly NotificationServiceInterface $notificationService
    ) {}

    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'target' => 'required|in:all,users,providers ,specific',
            'user_id' => 'required_if:target,specific|exists:users,id',
            'title' => 'required|string|max:150',
            'message' => 'required|string',
        ]);

        $result = $this->notificationService->sendBulkNotification(
            $request->string('target')->toString(),
            $request->string('title')->toString(),
            $request->string('message')->toString(),
            $request->integer('user_id') ?: null
        );

        if (! ($result['success'] ?? false)) {
            $status = str_contains((string) ($result['message'] ?? ''), 'Too many recipients')
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
    $users = \App\Modules\Users\Models\User::select('id', 'name')->get();

    return $this->success($users, 'Users list retrieved successfully');
}
}
