<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Modules\Locations\Models\Address; 

Route::prefix('v1')->group(function () {
    require base_path('app/Modules/Locations/routes/api.php');
    require base_path('app/Modules/Cities/routes/api.php');
});

Route::get('/test-addresses/{id}', function ($id) {
    $user = \App\Modules\Users\Models\User::find($id);
    if (!$user) {
        return response()->json(['message' => 'User not found'], 404);
    }
    // استدعاء الدالة التي تتواصل مع الـ Microservice
    return response()->json($user->fetchAddresses());
});