<?php
// app/Enums/RequestStatusEnum.php

namespace App\Enums;

enum RequestStatusEnum: string
{
    case Pending   = 'pending';
    case Accepted  = 'accepted';
    case Rejected  = 'rejected';
    case Delayed   = 'delayed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function isFinal(): bool
    {
        return in_array($this, [
            self::Rejected,
            self::Completed,
            self::Cancelled,
        ], true);
    }

    public static function finalValues(): array
    {
        return [
            self::Rejected->value,
            self::Completed->value,
            self::Cancelled->value,
        ];
    }
}
