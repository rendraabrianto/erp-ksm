<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'KSM')->first();
        $branch = Branch::where('code', 'HO')->first();

        $user = User::updateOrCreate(
            [
                'email' => 'superadmin@ksm.local',
            ],
            [
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );

        $user->assignRole('Super Admin');
    }
}