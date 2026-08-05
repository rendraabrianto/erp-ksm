<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ItemCategory;

class ItemCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [

            ['code'=>'PAKAN','name'=>'Pakan'],

            ['code'=>'OVK','name'=>'OVK'],

            ['code'=>'DOC','name'=>'DOC'],

            ['code'=>'PULLET','name'=>'Pullet'],

            ['code'=>'TELUR','name'=>'Telur'],

            ['code'=>'AYAM','name'=>'Ayam Hidup'],

            ['code'=>'AFKIR','name'=>'Ayam Afkir'],

            ['code'=>'PERALATAN','name'=>'Peralatan'],

            ['code'=>'SPAREPART','name'=>'Sparepart'],

        ];

        foreach ($categories as $category) {

            ItemCategory::firstOrCreate(
                ['code' => $category['code']],
                [
                    'name' => $category['name'],
                    'is_active' => true,
                ]
            );
        }
    }
}