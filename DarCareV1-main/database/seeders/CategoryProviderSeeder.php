<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Modules\Providers\Models\Provider; 
use App\Modules\Categories\Models\Category; 

class CategoryProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = Provider::all();
        $categories = Category::all();

        foreach ($providers as $provider) {
            $provider->categories()->attach(
                $categories->random(rand(1, 3))->pluck('id')->toArray()
            );
        }
    }
}
