<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Modules\ServiceRequests\Models\ServiceRequest; 
use App\Modules\Ratings\Models\Rating; 


class RatingSeeder extends Seeder
{
    public function run(): void
    {
        $requests = ServiceRequest::all();

        foreach ($requests as $request) {
            Rating::create([
                'user_id' => $request->user_id,
                'provider_id' => $request->provider_id,
                'service_request_id' => $request->id,
                'rating' => rand(3,5),
                'comment' => 'Good service',
            ]);
        }
    }

}
