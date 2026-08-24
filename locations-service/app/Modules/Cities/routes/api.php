<?php

use App\Modules\Cities\Http\Controllers\CityController;
use Illuminate\Support\Facades\Route;

Route::prefix('cities')->group(function () {
    Route::get('/', [CityController::class,'index']);
});