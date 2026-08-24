<?php

use App\Modules\Chat\Http\Controllers\Admin\AdminConversationController;
use App\Modules\Chat\Http\Controllers\ChatController;
use App\Modules\Chat\Http\Controllers\ConversationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::middleware('throttle:chat-open')->group(function () {
        Route::post('chat/conversations', [ConversationController::class, 'store']);
    });

    Route::get('chat/conversations', [ConversationController::class, 'index']);
    Route::get('chat/conversations/{conversation}', [ConversationController::class, 'show']);
    Route::get('chat/conversations/{conversation}/messages', [ConversationController::class, 'messages']);

    Route::middleware('throttle:chat-send')->group(function () {
        Route::post('chat/conversations/{conversation}/messages', [ConversationController::class, 'send']);
    });

    Route::middleware('throttle:chat-read')->group(function () {
        Route::patch('chat/conversations/{conversation}/read', [ConversationController::class, 'markRead']);
    });

    Route::patch('chat/conversations/{conversation}/mute', [ConversationController::class, 'mute']);
    Route::get('chat/unread-count', [ConversationController::class, 'unreadCount']);

    // Legacy compatibility wrappers
    Route::prefix('requests/{requestId}/messages')->group(function () {
        Route::get('/', [ChatController::class, 'index']);
        Route::middleware('throttle:chat-send')->post('/', [ChatController::class, 'send']);
    });
});

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin/chat')->group(function () {
    Route::get('conversations', [AdminConversationController::class, 'index']);
    Route::get('conversations/{conversation}', [AdminConversationController::class, 'show']);
    Route::get('conversations/{conversation}/messages', [AdminConversationController::class, 'messages']);
    Route::middleware('throttle:chat-send')->post('conversations/{conversation}/messages', [AdminConversationController::class, 'send']);
    Route::post('conversations/{conversation}/close', [AdminConversationController::class, 'close']);
    Route::post('conversations/{conversation}/reopen', [AdminConversationController::class, 'reopen']);
});
