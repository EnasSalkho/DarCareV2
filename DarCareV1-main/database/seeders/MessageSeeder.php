<?php

namespace Database\Seeders;

use App\Modules\Chat\Models\Message;
use App\Modules\ServiceRequests\Models\ServiceRequest;
use App\Modules\Users\Models\User;
use Illuminate\Database\Seeder;

class MessageSeeder extends Seeder
{
    public function run(): void
    {
        $requests = ServiceRequest::all();

        foreach ($requests as $request) {
            Message::create([
                'service_request_id' => $request->id,
                'sender_id' => $request->user_id,
                'sender_type' => (new User)->getMorphClass(),
                'body' => 'Hello, I need help!',
            ]);
        }
    }
}
