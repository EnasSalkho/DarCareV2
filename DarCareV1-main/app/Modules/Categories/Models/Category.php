<?php
// app/Modules/Categories/Models/Category.php

namespace App\Modules\Categories\Models;

use App\Modules\Providers\Models\Provider;
use App\Modules\ServiceRequests\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $table = 'categories';

    protected $fillable = ['name', 'slug', 'icon', 'description', 'is_active'];

    // 1. القسم يحتوي على العديد من الفنيين
    public function providers(): BelongsToMany
    {
        return $this->belongsToMany(Provider::class, 'category_provider', 'category_id', 'provider_id');
    }

    // 2. القسم تم طلبه في العديد من طلبات الخدمة
    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'category_id');
    }
}
