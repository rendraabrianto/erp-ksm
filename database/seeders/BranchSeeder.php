<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'KSM')->first();

        Branch::updateOrCreate(
            [
                'company_id' => $company->id,
                'code' => 'HO',
            ],
            [
                'name' => 'Head Office',
                'is_active' => true,
            ]
        );
    }
}