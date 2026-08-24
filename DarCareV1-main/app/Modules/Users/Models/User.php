<?php

namespace App\Modules\Users\Models;

//use App\Modules\Locations\Models\Address;
use App\Modules\ServiceRequests\Models\ServiceRequest;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Http;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'password',
        'profile_image',
        'role',
        'fcm_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'fcm_token',
    ];

    public function fetchAddresses(): array
{
    $response = Http::timeout(5)->get(
        'http://127.0.0.1:8001/api/v1/addresses/user/' . $this->id
    );

    if ($response->successful()) {
        return $response->json('data', []);
    }

    return [];
}

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    public function getMorphClass(): string
    {
        return 'user';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isCustomer(): bool
    {
        return $this->role === 'user';
    }

    // public function addresses(): MorphMany
    // {
    //     return $this->morphMany(Address::class, 'addressable');
    // }

    // public function primaryAddress(): MorphMany
    // {
    //     return $this->morphMany(Address::class, 'addressable')->where('is_primary', true);
    // }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'user_id');
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
