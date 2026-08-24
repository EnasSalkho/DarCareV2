<?php

namespace App\Modules\Notifications\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DeviceToken extends Model
{
    protected $fillable = [
        'tokenable_type',
        'tokenable_id',
        'token',
        'platform',
        'device_name',
        'device_identifier',
        'last_used_at',
        'invalidated_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }

    public function tokenable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isValid(): bool
    {
        return $this->invalidated_at === null;
    }

    public function scopeValid($query)
    {
        return $query->whereNull('invalidated_at');
    }
}
