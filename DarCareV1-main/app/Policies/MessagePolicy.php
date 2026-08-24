<?php

namespace App\Policies;

use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\Message;
use App\Modules\Chat\Support\ActorHelper;
use Illuminate\Support\Facades\Gate;

class MessagePolicy
{
    public function view(mixed $actor, Message $message): bool
    {
        $conversation = $message->relationLoaded('conversation')
            ? $message->conversation
            : $message->conversation()->first();

        if (! $conversation instanceof Conversation) {
            return false;
        }

        return Gate::forUser($actor)->allows('view', $conversation);
    }

    /**
     * Soft-delete own message only while still an active participant.
     */
    public function delete(mixed $actor, Message $message): bool
    {
        if (! is_object($actor)) {
            return false;
        }

        $isOwner = $message->sender_type === ActorHelper::morphType($actor)
            && (int) $message->sender_id === ActorHelper::morphId($actor);

        if (! $isOwner) {
            return false;
        }

        $conversation = $message->relationLoaded('conversation')
            ? $message->conversation
            : $message->conversation()->first();

        if (! $conversation instanceof Conversation) {
            return false;
        }

        return $conversation->participants()
            ->whereNull('left_at')
            ->where('participant_type', ActorHelper::morphType($actor))
            ->where('participant_id', ActorHelper::morphId($actor))
            ->exists();
    }
}
