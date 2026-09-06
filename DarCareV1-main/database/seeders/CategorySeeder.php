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
        // أزلنا حقل icon لأننا لم نعد بحاجة إليه
        $categories = [
            ['name' => 'Plumbing'],
            ['name' => 'Carpentry'],
            ['name' => 'Electrical'],
            ['name' => 'Painting'],
            ['name' => 'HVAC'],
            ['name' => 'Cleaning'],
            ['name' => 'Landscaping'],
            ['name' => 'Roofing'],
        ];

        foreach ($categories as $cat) {
            DB::table('categories')->insertOrIgnore([
                'name'       => $cat['name'],
                'slug'       => Str::slug($cat['name']),
                'image'      => 'categories/default.png', // تعيين مسار الصورة الافتراضية هنا
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}