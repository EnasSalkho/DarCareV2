<?php

namespace App\Modules\Providers\Models;

use App\Modules\Categories\Models\Category;
use App\Modules\Ratings\Models\Rating;
use App\Modules\ServiceRequests\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Http;
use App\Modules\Providers\Models\Provider;


class Provider extends Authenticatable
{
    use HasApiTokens, SoftDeletes, Notifiable;

    protected $table = 'providers';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'password',
        'years_of_experience',
        'bio',
        'profile_image',
        'status',
        'rating_avg',
        'fcm_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'fcm_token',
    ];

    protected function casts(): array
    {
        return [
            'rating_avg' => 'decimal:2',
        ];
    }

    public function getMorphClass(): string
    {
        return 'provider';
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_provider', 'provider_id', 'category_id');
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'provider_id');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class, 'provider_id');
    }

    

    public function conversationParticipations(): MorphMany
    {
        return $this->morphMany(\App\Modules\Chat\Models\ConversationParticipant::class, 'participant');
    }

    public function deviceTokens(): MorphMany
    {
        return $this->morphMany(\App\Modules\Notifications\Models\DeviceToken::class, 'tokenable');
    }

    public function sentMessages(): MorphMany
    {
        return $this->morphMany(\App\Modules\Chat\Models\Message::class, 'sender');
    }
}
