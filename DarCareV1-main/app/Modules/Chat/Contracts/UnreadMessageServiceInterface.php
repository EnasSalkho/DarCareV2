<?php

namespace App\Modules\Chat\Contracts;

use App\Modules\Chat\Models\Conversation;

interface UnreadMessageServiceInterface
{
    public function markRead(Conversation $conversation, object $actor, ?int $lastMessageId = null): void;

    /**
     * @return array{total: int, by_type: array<string, int>}
     */
    public function totalUnread(object $actor): array;

    public function unreadForConversation(Conversation $conversation, object $actor): int;
}
