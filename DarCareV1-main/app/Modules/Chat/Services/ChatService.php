<?php
// app/Modules/Chat/Services/ChatService.php

namespace App\Modules\Chat\Services;

use App\Modules\Chat\Contracts\ChatServiceInterface;
use App\Modules\Chat\Events\MessageSent;
use App\Modules\Chat\Models\Message;

class ChatService implements ChatServiceInterface
{
    public function getMessages(int $requestId): mixed
    {
        return Message::where('service_request_id', $requestId)
            ->orderBy('created_at')
            ->paginate(50);
    }

    public function sendMessage(int $requestId, object $sender, string $body): object
    {
        $message = Message::create([
            'service_request_id' => $requestId,
            'sender_type' => get_class($sender),
            'sender_id' => $sender->id,
            'body' => $body,
        ]);

        broadcast(new MessageSent($message));

        // Legacy path: prefer Conversation MessageService, which also stores
        // database notifications and sends FCM synchronously after commit.

        return $message;
    }
}
