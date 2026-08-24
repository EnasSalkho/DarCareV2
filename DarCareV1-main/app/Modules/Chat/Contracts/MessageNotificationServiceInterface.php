<?php

namespace App\Modules\Chat\Contracts;

use App\Modules\Chat\Models\Message;

interface MessageNotificationServiceInterface
{
    /**
     * Broadcast chat unread events and send FCM immediately for all eligible
     * recipients of a chat message. Chat messages are not persisted to the
     * Laravel notifications table.
     *
     * @return array{firebase: array{attempted: int, sent: int, failed: int, invalidated: int}}
     */
    public function notifyRecipientsImmediately(Message $message): array;
}
