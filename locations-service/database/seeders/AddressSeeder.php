<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Modules\Locations\Models\Address; 

class AddressSeeder extends Seeder
{
    // public function run(): void
    // {
    //     $users = User::all();

    //     foreach ($users as $user) {
    //         Address::create([
    //             'addressable_id' => $user->id,
    //             'addressable_type' => User::class,
    //             'latitude' => 33.5138,
    //             'longitude' => 36.2765,
    //             'label' => 'home',
    //             'is_primary' => true,
    //         ]);
    //     }
    // }
}
