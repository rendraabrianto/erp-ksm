<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\DocumentSequence;
use App\Models\ItemCategory;
use Illuminate\Database\Seeder;

class InventoryAdjustmentSetupSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Adjustment Gain
        |--------------------------------------------------------------------------
        */

        $gainAccount =
            Account::firstOrCreate(
                [
                    'code' => '4901',
                ],
                [
                    'account_group_id' => 4,
                    'name' =>
                        'Pendapatan Selisih Persediaan',

                    'normal_balance' =>
                        'CREDIT',

                    'is_header' =>
                        false,

                    'is_active' =>
                        true,
                ]
            );

        /*
        |--------------------------------------------------------------------------
        | Adjustment Loss
        |--------------------------------------------------------------------------
        */

        $lossAccount =
            Account::firstOrCreate(
                [
                    'code' => '6901',
                ],
                [
                    'account_group_id' => 6,
                    'name' =>
                        'Beban Selisih Persediaan',

                    'normal_balance' =>
                        'DEBIT',

                    'is_header' =>
                        false,

                    'is_active' =>
                        true,
                ]
            );

        /*
        |--------------------------------------------------------------------------
        | Map all inventory categories
        |--------------------------------------------------------------------------
        */

        ItemCategory::query()
            ->update([
                'adjustment_gain_account_id' =>
                    $gainAccount->id,

                'adjustment_loss_account_id' =>
                    $lossAccount->id,
            ]);

        /*
        |--------------------------------------------------------------------------
        | Document Sequence
        |--------------------------------------------------------------------------
        */

        DocumentSequence::firstOrCreate(
            [
                'document_type' =>
                    'ADJ',
            ],
            [
                'prefix' =>
                    'ADJ',

                'description' =>
                    'Inventory Adjustment',

                'current_number' =>
                    0,

                'padding' =>
                    5,

                'is_active' =>
                    true,
            ]
        );
    }
}