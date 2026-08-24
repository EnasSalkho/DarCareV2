<?php

namespace App\Models;

/**
 * Compatibility alias for the canonical user model.
 *
 * Prefer App\Modules\Users\Models\User in new code.
 * Both resolve to the same table and morph alias "user".
 */
class User extends \App\Modules\Users\Models\User
{
    //
}
