<?php
// app/Modules/Locations/routes/api.php

use App\Modules\Locations\Http\Controllers\LocationController;
use App\Modules\Locations\Http\Controllers\Admin\AdminLocationController;
use App\Modules\Locations\Services\LocationService;
use Illuminate\Support\Facades\Route;

Route::prefix('addresses')->name('addresses.')->group(function () {

    // 1. أضيفي مسار البحث عن القريبين هنا (في البداية)
    Route::get('/nearby-owners', [LocationController::class, 'nearbyOwners'])->name('nearby-owners');

    Route::get('/id/{addressId}', [LocationController::class, 'show'])
        ->name('show');
    // جلب عناوين مالك معين
    Route::get('/{owner_type}/{owner_id}', [LocationController::class, 'index'])->name('index');
    
    // إنشاء عنوان جديد
    Route::post('/', [LocationController::class, 'store'])->name('store');
    
    
    // تعيين كعنوان أساسي
    Route::patch('/{owner_type}/{owner_id}/{addressId}/primary', [LocationController::class, 'setPrimary'])->name('primary');

    // حذف عنوان
    Route::delete('/{owner_type}/{owner_id}/{addressId}', [LocationController::class, 'destroy'])->name('destroy');
});

// مسار الأدمن
Route::prefix('admin')->group(function () {
    Route::get(
        'locations',
        [AdminLocationController::class, 'index']
    );
});

