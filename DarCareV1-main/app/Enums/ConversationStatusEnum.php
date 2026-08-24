<?php

namespace App\Enums;

enum ConversationStatusEnum: string
{
    case Open = 'open';
    case Closed = 'closed';
    case ReadOnly = 'read_only';
}
