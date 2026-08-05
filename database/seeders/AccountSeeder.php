<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Account;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [

            // ASSET

            [
                'account_group_id'=>1,
                'code'=>'1001',
                'name'=>'Kas',
                'normal_balance'=>'DEBIT'
            ],

            [
                'account_group_id'=>1,
                'code'=>'1002',
                'name'=>'Bank BCA',
                'normal_balance'=>'DEBIT'
            ],

            [
                'account_group_id'=>1,
                'code'=>'1101',
                'name'=>'Piutang Dagang',
                'normal_balance'=>'DEBIT'
            ],

            [
                'account_group_id'=>1,
                'code'=>'1201',
                'name'=>'Persediaan Pakan',
                'normal_balance'=>'DEBIT'
            ],

            [
                'account_group_id'=>1,
                'code'=>'1202',
                'name'=>'Persediaan OVK',
                'normal_balance'=>'DEBIT'
            ],

            [
                'account_group_id'=>1,
                'code'=>'1203',
                'name'=>'Persediaan DOC',
                'normal_balance'=>'DEBIT'
            ],

            // LIABILITY

            [
                'account_group_id'=>2,
                'code'=>'2001',
                'name'=>'Hutang Dagang',
                'normal_balance'=>'CREDIT'
            ],

            // EQUITY

            [
                'account_group_id'=>3,
                'code'=>'3001',
                'name'=>'Modal Pemilik',
                'normal_balance'=>'CREDIT'
            ],

            // REVENUE

            [
                'account_group_id'=>4,
                'code'=>'4001',
                'name'=>'Penjualan Pakan',
                'normal_balance'=>'CREDIT'
            ],

            [
                'account_group_id'=>4,
                'code'=>'4002',
                'name'=>'Penjualan DOC',
                'normal_balance'=>'CREDIT'
            ],

            // HPP

            [
                'account_group_id'=>5,
                'code'=>'5001',
                'name'=>'HPP Pakan',
                'normal_balance'=>'DEBIT'
            ],

            [
                'account_group_id'=>5,
                'code'=>'5002',
                'name'=>'HPP DOC',
                'normal_balance'=>'DEBIT'
            ],

            // EXPENSE

            [
                'account_group_id'=>6,
                'code'=>'6001',
                'name'=>'Biaya Gaji',
                'normal_balance'=>'DEBIT'
            ],

            [
                'account_group_id'=>6,
                'code'=>'6002',
                'name'=>'Biaya Listrik',
                'normal_balance'=>'DEBIT'
            ],
        ];

        foreach ($accounts as $account)
        {
            Account::firstOrCreate(
                ['code'=>$account['code']],
                $account
            );
        }
    }
}