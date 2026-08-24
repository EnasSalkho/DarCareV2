<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Providers\Models\Provider;
use App\Modules\Categories\Models\Category;
use Illuminate\Support\Facades\Hash;

class ProviderSeeder extends Seeder
{
    public function run(): void
    {
        $provider1 = Provider::create([
            'name' => 'Electrician Ahmad',
            'email' => 'provider1@test.com',
            'phone' => '0777777777',
            'password' => Hash::make('12345678'),
            'years_of_experience' => 5,
            'bio' => 'Expert electrician',
            'profile_image' => 'test.jpg',
            'status' => 'available',
        ]);

        $provider2 = Provider::create([
            'name' => 'Plumber Ali',
            'email' => 'provider2@test.com',
            'phone' => '0788888888',
            'password' => Hash::make('12345678'),
            'years_of_experience' => 3,
            'bio' => 'Professional plumber',
            'profile_image' => 'test.jpg',
            'status' => 'available',
        ]);

        $category = Category::first();

        if ($category) {
            $provider1->categories()->attach($category->id);
            $provider2->categories()->attach($category->id);
        }
    }
}