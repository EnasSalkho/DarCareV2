<?php

use App\Modules\ServiceRequests\Http\Controllers\Admin\AdminServiceRequestController;
use App\Modules\ServiceRequests\Http\Controllers\ServiceRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('requests')->name('requests.')->group(function () {
    // User
    Route::post('/',                [ServiceRequestController::class, 'store'])->name('store');
    Route::get('/my',               [ServiceRequestController::class, 'userRequests'])->name('my');

    // Provider
    Route::get('/incoming',         [ServiceRequestController::class, 'providerRequests'])->name('incoming');
    Route::patch('/{id}/status',    [ServiceRequestController::class, 'updateStatus'])->name('updateStatus');
    

    // Shared
    Route::get('/{id}',             [ServiceRequestController::class, 'show'])->name('show');
});

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('service-requests', [AdminServiceRequestController::class, 'index']);
    Route::get('service-requests/{id}', [AdminServiceRequestController::class, 'show']);
    Route::patch('service-requests/{id}/reassign', [AdminServiceRequestController::class, 'reassign']);
});
