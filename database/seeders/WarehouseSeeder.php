<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Warehouse;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        Warehouse::firstOrCreate(
            ['code' => 'WH-001'],
            [
                'company_id' => 1,
                'branch_id'  => 1,
                'name'       => 'Gudang Utama KSM',
                'phone'      => null,
                'email'      => null,
                'address'    => null,
                'is_active'  => true,
            ]
        );
    }
}