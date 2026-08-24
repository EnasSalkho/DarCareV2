<?php

namespace App\Modules\Favorites\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Providers\Models\Provider;
use App\Modules\Users\Models\User;

class Favorite extends Model
{
    protected $table = 'favorites';
    protected $fillable = ['user_id', 'provider_id'];

    public function provider()
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
