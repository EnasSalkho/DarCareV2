<?php

namespace App\Modules\Chat\Services;

use App\Modules\Chat\Contracts\UnreadMessageServiceInterface;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\ConversationParticipant;
use App\Modules\Chat\Models\Message;
use App\Modules\Chat\Support\ActorHelper;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\Gate;

class UnreadMessageService implements UnreadMessageServiceInterface
{
    public function markRead(Conversation $conversation, object $actor, ?int $lastMessageId = null): void
    {
        Gate::forUser($actor)->authorize('markRead', $conversation);

        $participant = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->whereNull('left_at')
            ->where('participant_type', ActorHelper::morphType($actor))
            ->where('participant_id', ActorHelper::morphId($actor))
            ->first();

        if (! $participant) {
            // Admins may mark support threads read via policy without a participant row.
            if ($actor instanceof User && $actor->isAdmin()) {
                return;
            }

            abort(403, 'Not an active participant of this conversation.');
        }

        $targetId = $lastMessageId ?? $conversation->last_message_id;

        if ($targetId === null) {
            return;
        }

        if (
            $participant->last_read_message_id !== null
            && (int) $targetId < (int) $participant->last_read_message_id
        ) {
            return;
        }

        $participant->update([
            'last_read_message_id' => $targetId,
            'last_read_at' => now(),
        ]);
    }

    public function totalUnread(object $actor): array
    {
        $byType = [
            'request' => 0,
            'support_customer' => 0,
            'support_provider' => 0,
        ];

        $participations = ConversationParticipant::query()
            ->whereNull('left_at')
            ->where('participant_type', ActorHelper::morphType($actor))
            ->where('participant_id', ActorHelper::morphId($actor))
            ->with('conversation')
            ->get();

        $total = 0;

        foreach ($participations as $participation) {
            $conversation = $participation->conversation;

            if (! $conversation) {
                continue;
            }

            $count = $this->countUnreadMessages($conversation, $actor, $participation);
            $typeKey = $conversation->type instanceof \BackedEnum
                ? $conversation->type->value
                : (string) $conversation->type;

            $byType[$typeKey] = ($byType[$typeKey] ?? 0) + $count;
            $total += $count;
        }

        return [
            'total' => $total,
            'by_type' => $byType,
        ];
    }

    public function unreadForConversation(Conversation $conversation, object $actor): int
    {
        $participant = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->whereNull('left_at')
            ->where('participant_type', ActorHelper::morphType($actor))
            ->where('participant_id', ActorHelper::morphId($actor))
            ->first();

        if (! $participant) {
            return 0;
        }

        return $this->countUnreadMessages($conversation, $actor, $participant);
    }

    private function countUnreadMessages(
        Conversation $conversation,
        object $actor,
        ConversationParticipant $participant
    ): int {
        $query = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where(function ($q) use ($actor) {
                $q->where('sender_type', '<>', ActorHelper::morphType($actor))
                    ->orWhere(function ($inner) use ($actor) {
                        $inner->where('sender_type', ActorHelper::morphType($actor))
                            ->where('sender_id', '<>', ActorHelper::morphId($actor));
                    });
            });

        if ($participant->last_read_message_id !== null) {
            $query->where('id', '>', $participant->last_read_message_id);
        }

        return (int) $query->count();
    }
}
