<?php

namespace App\Modules\Chat\Services;

use App\Modules\Chat\Contracts\MessageNotificationServiceInterface;
use App\Modules\Chat\Contracts\MessageServiceInterface;
use App\Modules\Chat\Events\MessageSent;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\Message;
use App\Modules\Chat\Support\ActorHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class MessageService implements MessageServiceInterface
{
    public function __construct(
        private readonly MessageNotificationServiceInterface $messageNotifications
    ) {}

    public function send(
        Conversation $conversation,
        object $actor,
        string $body,
        ?int $replyTo = null,
        ?string $clientMessageId = null
    ): Message {
        Gate::forUser($actor)->authorize('sendMessage', $conversation);

        $body = trim($body);

        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => 'Message body cannot be blank.',
            ]);
        }

        if (mb_strlen($body) > 5000) {
            throw ValidationException::withMessages([
                'body' => 'Message body may not exceed 5000 characters.',
            ]);
        }

        $senderType = ActorHelper::morphType($actor);
        $senderId = ActorHelper::morphId($actor);

        if ($clientMessageId !== null && $clientMessageId !== '') {
            $existing = Message::query()
                ->where('conversation_id', $conversation->id)
                ->where('sender_type', $senderType)
                ->where('sender_id', $senderId)
                ->where('client_message_id', $clientMessageId)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        if ($replyTo !== null) {
            $replyExists = Message::query()
                ->where('conversation_id', $conversation->id)
                ->whereKey($replyTo)
                ->exists();

            if (! $replyExists) {
                throw ValidationException::withMessages([
                    'reply_to' => 'Reply target message was not found in this conversation.',
                ]);
            }
        }

        $message = DB::transaction(function () use (
            $conversation,
            $body,
            $replyTo,
            $clientMessageId,
            $senderType,
            $senderId
        ) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'service_request_id' => $conversation->service_request_id,
                'sender_type' => $senderType,
                'sender_id' => $senderId,
                'type' => 'text',
                'body' => $body,
                'reply_to_message_id' => $replyTo,
                'client_message_id' => $clientMessageId ?: null,
            ]);

            $conversation->update([
                'last_message_id' => $message->id,
                'last_message_at' => $message->created_at,
            ]);

            return $message;
        });

        $message->load(['sender', 'conversation.participants.participant']);

        try {
            broadcast(new MessageSent($message));
        } catch (Throwable $e) {
            Log::warning('Chat MessageSent broadcast failed', [
                'service' => 'pusher',
                'conversation_id' => $message->conversation_id,
                'message_id' => $message->id,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $this->messageNotifications->notifyRecipientsImmediately($message);
        } catch (Throwable $e) {
            Log::warning('Chat recipient notification fan-out failed', [
                'service' => 'message_notifications',
                'conversation_id' => $message->conversation_id,
                'message_id' => $message->id,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);
        }

        return $message;
    }
}
