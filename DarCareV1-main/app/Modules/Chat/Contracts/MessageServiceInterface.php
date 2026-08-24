<?php

namespace App\Modules\Chat\Contracts;

use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\Message;

interface MessageServiceInterface
{
    public function send(
        Conversation $conversation,
        object $actor,
        string $body,
        ?int $replyTo = null,
        ?string $clientMessageId = null
    ): Message;
}
