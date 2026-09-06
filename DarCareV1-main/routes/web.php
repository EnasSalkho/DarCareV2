<?php

use App\Modules\Chat\Models\Message;
use App\Modules\Chat\Events\MessageSent;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/listen', 'test-listen');

Route::get('/test-broadcast', function () {
    abort_unless(app()->environment('local'), 404);

    $user = request()->user();
    abort_unless($user, 401);
    abort_unless(
        ($user instanceof \App\Modules\Users\Models\User && $user->isAdmin()),
        403
    );

    $message = Message::latest('id')->first();

    if (! $message) {
        return response()->json([
            'success' => false,
            'message' => 'No messages available to broadcast.',
        ], 404);
    }

    broadcast(new MessageSent($message));

    return response()->json([
        'success' => true,
        'message' => 'Broadcast sent.',
        'data' => ['message_id' => $message->id],
    ]);
})->middleware('auth:sanctum');
