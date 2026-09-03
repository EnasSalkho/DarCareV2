<?php
// app/Modules/Notifications/Models/Notification.php

namespace App\Modules\Notifications\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Notification extends Model
{
    use HasUuids;

    protected $fillable = ['batch_id', 'notifiable_type', 'notifiable_id', 'type', 'audience', 'data', 'read_at'];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    /**
     * المستلم: user أو provider حسب الـ morph map.
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }
}
