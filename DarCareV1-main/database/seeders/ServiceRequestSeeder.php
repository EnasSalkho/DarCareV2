<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Modules\Providers\Models\Provider; 
use App\Modules\Categories\Models\Category; 
use App\Modules\ServiceRequests\Models\ServiceRequest; 

class ServiceRequestSeeder extends Seeder
{
    public function run(): void
    {
        ServiceRequest::create([
            'user_id' => User::inRandomOrder()->first()->id,
            'provider_id' => Provider::inRandomOrder()->first()->id,
            'category_id' => Category::inRandomOrder()->first()->id,
            'description' => ''
        ]);
    }
}
