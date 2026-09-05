<?php

use App\Modules\Notifications\Http\Controllers\Admin\AdminNotificationController;
use App\Modules\Notifications\Http\Controllers\NotificationController;
use App\Modules\Notifications\Http\Controllers\DeviceTokenController;
use Illuminate\Support\Facades\Route;


// روابط جلب الإشعارات الخاصة بالمستخدم (تحتاج توكن)
Route::middleware('auth:sanctum')->prefix('notifications')->name('notifications.')->group(function () {

    // هذا الرابط يجلب الإشعارات الرئيسية
    Route::get('/', [NotificationController::class, 'index'])->name('index');

    // هذا الرابط لتحديد الكل كمقروء
    Route::patch('/read-all', [NotificationController::class, 'markAllRead'])->name('markAllRead');

    // هذا الرابط لتحديد إشعار واحد فقط كمقروء
    Route::patch('/{notification}/read', [NotificationController::class, 'markRead'])->name('markRead');

    // هذا الرابط لحذف إشعار واحد
    Route::delete('/{notification}', [NotificationController::class, 'destroy'])->name('destroy');

    Route::post('device-tokens', [DeviceTokenController::class, 'store'])
            ->name('device-tokens.store');

        Route::delete('device-tokens', [DeviceTokenController::class, 'destroy'])
            ->name('device-tokens.destroy');

});

// روابط الإشعارات الخاصة بالآدمن
Route::prefix('admin')->group(function () {
    Route::post('notifications/send-bulk', [AdminNotificationController::class, 'send']);

    Route::get('notifications/users-list', [AdminNotificationController::class, 'getUsersList']);
});
