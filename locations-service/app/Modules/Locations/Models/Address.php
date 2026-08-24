<?php
// app/Modules/Locations/Models/Address.php

namespace App\Modules\Locations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Address extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'addressable_type',
        'addressable_id',
        'latitude',
        'longitude',
        'label',
        'is_primary',
    ];

    protected $casts = [
        'latitude'   => 'decimal:8',
        'longitude'  => 'decimal:8',
        'is_primary' => 'boolean',
    ];

    //   جلب الموديل الأب تلقائياً (سواء كان User أو Provider)
    public function addressable(): MorphTo
    {
        return $this->morphTo();
    }
}
