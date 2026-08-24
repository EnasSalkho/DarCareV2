<?php
// app/Modules/ServiceRequests/Models/ServiceRequest.php

namespace App\Modules\ServiceRequests\Models;

use App\Enums\RequestStatusEnum;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Users\Models\User;
use App\Modules\Providers\Models\Provider;
use App\Modules\Categories\Models\Category;
use App\Modules\Locations\Models\Address;
use App\Modules\Ratings\Models\Rating;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceRequest extends Model
{
    use SoftDeletes;

    protected $table = 'service_requests';

    protected $fillable = [
        'user_id', 'provider_id', 'category_id', 'address_id',
        'description', 'urgency', 'image', 'status',
        'temp_latitude', 'temp_longitude', 'temp_label', 'scheduled_at'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'address_id');
    }

    public function rating(): HasOne
    {
        return $this->hasOne(Rating::class, 'service_request_id');
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class)->where('type', 'request');
    }

    public function isFinalStatus(): bool
    {
        $status = RequestStatusEnum::tryFrom((string) $this->status);

        return $status?->isFinal() ?? false;
    }
}
