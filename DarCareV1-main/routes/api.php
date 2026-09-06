<?php
// routes/api.php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast; // أضيفي هذا الكلاس

// تعريف راوت البث ليعمل بنظام API مع Sanctum
Broadcast::routes(['middleware' => ['auth:sanctum']]);


Route::prefix('v1')->group(function () {
    require base_path('app/Modules/Auth/routes/api.php');
    require base_path('app/Modules/Users/routes/api.php');
    require base_path('app/Modules/Providers/routes/api.php');
    require base_path('app/Modules/Categories/routes/api.php');
    require base_path('app/Modules/ServiceRequests/routes/api.php');
    require base_path('app/Modules/Chat/routes/api.php');
    require base_path('app/Modules/Notifications/routes/api.php');
    require base_path('app/Modules/Favorites/routes/api.php');
    require base_path('app/Modules/Ratings/routes/api.php');
    require base_path('app/Modules/Dashboard/routes/api.php');
});