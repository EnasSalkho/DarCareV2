<?php
// database/seeders/CategorySeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Plumbing',       'icon' => '🔧'],
            ['name' => 'Carpentry',      'icon' => '🪚'],
            ['name' => 'Electrical',     'icon' => '⚡'],
            ['name' => 'Painting',       'icon' => '🎨'],
            ['name' => 'HVAC',           'icon' => '❄️'],
            ['name' => 'Cleaning',       'icon' => '🧹'],
            ['name' => 'Landscaping',    'icon' => '🌿'],
            ['name' => 'Roofing',        'icon' => '🏠'],
        ];

        foreach ($categories as $cat) {
            DB::table('categories')->insertOrIgnore([
                'name'       => $cat['name'],
                'slug'       => Str::slug($cat['name']),
                'icon'       => $cat['icon'],
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
