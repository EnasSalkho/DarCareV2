<?php

namespace App\Modules\Notifications\Events;

use App\Modules\Notifications\Models\Notification;
use Illuminate\Broadcasting\Channel; // حالياً خليناها عامة للاختبار السريع
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow; // التغيير هون!
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Notification $notification) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('notifications.' . $this->notification->notifiable_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'id'         => $this->notification->id,
            'type'       => $this->notification->type,
            'data'       => $this->notification->data,
            'created_at' => $this->notification->created_at,
        ];
    }
}