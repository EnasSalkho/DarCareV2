<?php

namespace App\Modules\Chat\Support;

use App\Modules\Providers\Models\Provider;
use App\Modules\Users\Models\User;
use InvalidArgumentException;

class ActorHelper
{
    public static function morphType(object $actor): string
    {
        if (method_exists($actor, 'getMorphClass')) {
            return $actor->getMorphClass();
        }

        throw new InvalidArgumentException('Actor does not support morph mapping.');
    }

    public static function morphId(object $actor): int
    {
        return (int) $actor->getKey();
    }

    public static function displayRole(object $actor): string
    {
        if ($actor instanceof User) {
            return $actor->isAdmin() ? 'support' : 'customer';
        }

        if ($actor instanceof Provider) {
            return 'artisan';
        }

        throw new InvalidArgumentException('Unsupported actor type.');
    }

    public static function channelName(object $actor): string
    {
        return 'user.'.self::morphType($actor).'.'.self::morphId($actor);
    }

    public static function isSameActor(?object $a, ?object $b): bool
    {
        if ($a === null || $b === null) {
            return false;
        }

        return self::morphType($a) === self::morphType($b)
            && self::morphId($a) === self::morphId($b);
    }
}
