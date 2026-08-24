<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Database\Seeders\ProviderSeeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ProviderSeeder::class,
            CategorySeeder::class,
            CategoryProviderSeeder::class,
            ServiceRequestSeeder::class,
            MessageSeeder::class,
            RatingSeeder::class,
        ]);
    }
}