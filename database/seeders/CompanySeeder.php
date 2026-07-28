<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        Company::updateOrCreate(
            ['code' => 'KSM'],
            [
                'name' => 'KSM GROUP',
                'is_active' => true,
            ]
        );
    }
}