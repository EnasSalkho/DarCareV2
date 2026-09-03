<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Notifications\Http\Resources\NotificationResource;
use App\Modules\Notifications\Http\Resources\SentNotificationResource;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Users\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class NotificationController extends Controller
{
    use ApiResponseTrait;

    /**
     * أنواع الإشعارات يلي بيبعتها الأدمن من لوحة التحكم.
     */
    private const ADMIN_SENT_TYPES = ['admin_bulk', 'admin_direct'];

    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();

        // الأدمن ما إلو إشعارات شخصية من اللوحة، فبيشوف سجل الإشعارات المرسلة
        // (للكل أو لمستخدم معين) — سطر واحد لكل إرسالة.
        if ($actor instanceof User && $actor->isAdmin()) {
            return $this->sentNotifications($request);
        }

        $query = $actor->notifications()->latest();

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        $notifications = $query->paginate((int) $request->integer('per_page', 15));

        return $this->success(NotificationResource::collection($notifications));
    }

    /**
     * الإرسالة الوحدة بتولّد سطر لكل مستلم، فمنجمّعهم بالـ batch_id
     * ليطلع سطر واحد بس بجدول الأدمن مهما كان عدد المستلمين.
     */
    private function sentNotifications(Request $request): JsonResponse
    {
        $query = Notification::query()
            ->whereIn('type', self::ADMIN_SENT_TYPES)
            ->whereNotNull('batch_id')
            ->selectRaw('batch_id, MAX(type) as type, MAX(audience) as audience, COUNT(*) as recipients_count')
            ->selectRaw('SUM(CASE WHEN read_at IS NOT NULL THEN 1 ELSE 0 END) as read_count')
            ->selectRaw('MAX(created_at) as sent_at')
            ->groupBy('batch_id')
            ->orderByDesc('sent_at');

        if ($request->filled('audience')) {
            $query->where('audience', $request->string('audience')->toString());
        }

        $batches = $query->paginate((int) $request->integer('per_page', 15));

        $samples = $this->loadBatchSamples($batches->getCollection()->pluck('batch_id'));

        $batches->setCollection(
            $batches->getCollection()
                ->map(fn ($batch) => [
                    'batch_id' => $batch->batch_id,
                    'audience' => $batch->audience,
                    'recipients_count' => (int) $batch->recipients_count,
                    'read_count' => (int) $batch->read_count,
                    'sample' => $samples->get($batch->batch_id),
                ])
                ->filter(fn ($batch) => $batch['sample'] !== null)
                ->values()
        );

        return $this->success(SentNotificationResource::collection($batches));
    }

    /**
     * سطر واحد يمثّل كل batch، مشان نجيب العنوان والمحتوى بدون ما نحمّل كل المستلمين.
     *
     * @param  \Illuminate\Support\Collection<int, string>  $batchIds
     * @return \Illuminate\Support\Collection<string, Notification>
     */
    private function loadBatchSamples($batchIds)
    {
        if ($batchIds->isEmpty()) {
            return collect();
        }

        $sampleIds = Notification::query()
            ->whereIn('batch_id', $batchIds)
            ->selectRaw('MIN(id) as id')
            ->groupBy('batch_id')
            ->pluck('id');

        return Notification::query()
            ->with('notifiable')
            ->whereIn('id', $sampleIds)
            ->get()
            ->keyBy('batch_id');
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $record = $request->user()
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        if ($record->read_at === null) {
            $record->markAsRead();
        }

        return $this->success(new NotificationResource($record->fresh()), 'Notification marked as read');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return $this->success(null, 'Notifications marked as read');
    }

    public function destroy(Request $request, string $notification): JsonResponse
    {
        $record = $request->user()
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        $record->delete();

        return $this->success(null, 'Notification deleted');
    }
}
