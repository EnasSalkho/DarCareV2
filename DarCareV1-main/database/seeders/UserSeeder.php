<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Enums\RoleEnum;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {

     User::create([
            'name'     => 'Admin',
            'phone'    => '0988888888',
            'email'    => 'admin@test.com',
            'password' => Hash::make('password123'),
            'role' => RoleEnum::Admin->value
        ]);


          User::create([
            'name' => 'Mahdia User',
            'phone' => '0999966999',
            'email' => 'mahdia@test.com',
            'password' => Hash::make('password'),
            'role' => 'user'
        ]);

          User::create([
            'name' => 'Enas User',
            'phone' => '0999999999',
            'email' => 'enas@test.com',
            'password' => Hash::make('password'),
            'role' => 'user'
        ]);



    }
}
