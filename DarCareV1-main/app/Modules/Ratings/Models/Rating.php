<?php
// app/Modules/Ratings/Models/Rating.php

namespace App\Modules\Ratings\Models;

use App\Modules\Users\Models\User;
use App\Modules\Providers\Models\Provider;
use App\Modules\ServiceRequests\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rating extends Model
{
    protected $table = 'ratings';

    protected $fillable = ['user_id', 'provider_id', 'service_request_id', 'rating', 'comment'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'service_request_id');
    }
}
