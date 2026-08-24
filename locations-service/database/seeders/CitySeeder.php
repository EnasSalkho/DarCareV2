<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Modules\Cities\Models\City;

class CitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        City::insert([
            ['name' => 'Damascus','name_ar' => 'دمشق'],
            ['name' => 'Aleppo','name_ar' => 'حلب'],
            ['name' => 'Homs','name_ar' => 'حمص'],
            ['name' => 'Hama','name_ar' => 'حماة'],
            ['name' => 'Latakia','name_ar' => 'الاذقية'],
            ['name' => 'Tartus','name_ar' => 'طرطوس'],
            ['name' => 'Idlib','name_ar' => 'ادلب'],
            ['name' => 'Raqqa','name_ar' => 'الرقة'],
            ['name' => 'Deir Ezzor','name_ar' => 'ديرالزور'],
            ['name' => 'Hasakah','name_ar' => 'الحسكة'],
            ['name' => 'Daraa','name_ar' => 'درعا'],
            ['name' => 'As-Suwayda','name_ar' => 'السويدة'],
            ['name' => 'Quneitra','name_ar' => 'القنيطرة'],
        ]);
    }
}
                                                                                    