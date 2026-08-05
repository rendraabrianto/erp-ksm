<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AccountGroup;

class AccountGroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [

            ['code'=>'1','name'=>'Asset'],
            ['code'=>'2','name'=>'Liability'],
            ['code'=>'3','name'=>'Equity'],
            ['code'=>'4','name'=>'Revenue'],
            ['code'=>'5','name'=>'Cost Of Goods Sold'],
            ['code'=>'6','name'=>'Expense'],

        ];

        foreach ($groups as $group)
        {
            AccountGroup::firstOrCreate(
                ['code'=>$group['code']],
                [
                    'name'=>$group['name'],
                    'is_active'=>true,
                ]
            );
        }
    }
}