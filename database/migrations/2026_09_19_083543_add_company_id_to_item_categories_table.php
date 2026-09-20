<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Resolve Legacy Company
        |--------------------------------------------------------------------------
        |
        | Existing ERP KSM production/development data currently belongs to one
        | company. Fresh test databases may have no company yet.
        |
        */

        $companyIds =
            DB::table('companies')
                ->orderBy('id')
                ->pluck('id');

        if ($companyIds->count() > 1) {
            throw new \RuntimeException(
                'Item category company isolation cannot infer legacy ownership when multiple companies exist.'
            );
        }

        $legacyCompanyId =
            $companyIds->count() === 1
                ? (int) $companyIds->first()
                : null;

        /*
        |--------------------------------------------------------------------------
        | Add Nullable Company ID
        |--------------------------------------------------------------------------
        */

        Schema::table(
            'item_categories',
            function (Blueprint $table) {
                $table->foreignId('company_id')
                    ->nullable()
                    ->after('id')
                    ->index();
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Backfill Existing Legacy Categories
        |--------------------------------------------------------------------------
        */

        if ($legacyCompanyId !== null) {
            DB::table('item_categories')
                ->whereNull('company_id')
                ->update([
                    'company_id' =>
                        $legacyCompanyId,
                ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Category Ownership
        |--------------------------------------------------------------------------
        */

        $categoryWithoutCompany =
            DB::table('item_categories')
                ->whereNull('company_id')
                ->exists();

        if ($categoryWithoutCompany) {
            throw new \RuntimeException(
                'One or more item categories do not have company ownership.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Accounting Account Ownership
        |--------------------------------------------------------------------------
        */

        $accountColumns = [
            'inventory_account_id',
            'cogs_account_id',
            'sales_account_id',
            'adjustment_gain_account_id',
            'adjustment_loss_account_id',
        ];

        foreach ($accountColumns as $column) {

            $invalidMapping =
                DB::table('item_categories as ic')
                    ->join(
                        'accounts as a',
                        "a.id",
                        '=',
                        "ic.{$column}"
                    )
                    ->whereNotNull(
                        "ic.{$column}"
                    )
                    ->whereColumn(
                        'a.company_id',
                        '<>',
                        'ic.company_id'
                    )
                    ->exists();

            if ($invalidMapping) {
                throw new \RuntimeException(
                    "Item category {$column} contains an account from another company."
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Replace Global Code Uniqueness
        |--------------------------------------------------------------------------
        */

        Schema::table(
            'item_categories',
            function (Blueprint $table) {

                $table->dropUnique(
                    'item_categories_code_unique'
                );

                $table->unique(
                    [
                        'company_id',
                        'code',
                    ],
                    'item_categories_company_code_unique'
                );

                $table->foreign('company_id')
                    ->references('id')
                    ->on('companies')
                    ->restrictOnDelete();
            }
        );
    }

    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Safety Check Before Restoring Global Code Uniqueness
        |--------------------------------------------------------------------------
        */

        $duplicateCode =
            DB::table('item_categories')
                ->select(
                    'code',
                    DB::raw('COUNT(*) as total')
                )
                ->groupBy('code')
                ->havingRaw('COUNT(*) > 1')
                ->exists();

        if ($duplicateCode) {
            throw new \RuntimeException(
                'Cannot rollback item category company isolation because duplicate category codes exist across companies.'
            );
        }

        Schema::table(
            'item_categories',
            function (Blueprint $table) {

                $table->dropForeign([
                    'company_id',
                ]);

                $table->dropUnique(
                    'item_categories_company_code_unique'
                );

                $table->unique(
                    'code',
                    'item_categories_code_unique'
                );

                $table->dropColumn(
                    'company_id'
                );
            }
        );
    }
};