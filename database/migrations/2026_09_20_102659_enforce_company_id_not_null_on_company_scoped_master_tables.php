<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    /**
     * Company-scoped master tables whose ownership has already
     * been backfilled and validated in previous migrations.
     */
    private array $tables = [
        'account_groups',
        'accounts',
        'item_categories',
        'items',
    ];

    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Preflight - NULL Company Ownership
        |--------------------------------------------------------------------------
        |
        | Never enforce NOT NULL while legacy rows still have no company.
        |
        */

        foreach ($this->tables as $table) {
            $nullCount =
                DB::table($table)
                    ->whereNull('company_id')
                    ->count();

            if ($nullCount > 0) {
                throw new \RuntimeException(
                    "Cannot enforce {$table}.company_id NOT NULL: "
                    . "{$nullCount} row(s) have NULL company_id."
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Preflight - Orphan Company Ownership
        |--------------------------------------------------------------------------
        */

        foreach ($this->tables as $table) {
            $orphanCount =
                DB::table($table . ' as master')
                    ->leftJoin(
                        'companies as company',
                        'company.id',
                        '=',
                        'master.company_id'
                    )
                    ->whereNull('company.id')
                    ->count();

            if ($orphanCount > 0) {
                throw new \RuntimeException(
                    "Cannot enforce {$table}.company_id NOT NULL: "
                    . "{$orphanCount} row(s) reference an invalid company."
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Preflight - Account Group Ownership
        |--------------------------------------------------------------------------
        */

        $crossCompanyAccountGroups =
            DB::table('accounts as account')
                ->join(
                    'account_groups as account_group',
                    'account_group.id',
                    '=',
                    'account.account_group_id'
                )
                ->whereColumn(
                    'account.company_id',
                    '<>',
                    'account_group.company_id'
                )
                ->count();

        if ($crossCompanyAccountGroups > 0) {
            throw new \RuntimeException(
                'Cannot enforce accounting master company ownership: '
                . "{$crossCompanyAccountGroups} account(s) belong to a "
                . 'different company than their account group.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Preflight - Item Category Ownership
        |--------------------------------------------------------------------------
        */

        $crossCompanyItemCategories =
            DB::table('items as item')
                ->join(
                    'item_categories as category',
                    'category.id',
                    '=',
                    'item.item_category_id'
                )
                ->whereColumn(
                    'item.company_id',
                    '<>',
                    'category.company_id'
                )
                ->count();

        if ($crossCompanyItemCategories > 0) {
            throw new \RuntimeException(
                'Cannot enforce item master company ownership: '
                . "{$crossCompanyItemCategories} item(s) belong to a "
                . 'different company than their item category.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Enforce NOT NULL
        |--------------------------------------------------------------------------
        */

        Schema::table('account_groups', function (Blueprint $table) {
            $table
                ->unsignedBigInteger('company_id')
                ->nullable(false)
                ->change();
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table
                ->unsignedBigInteger('company_id')
                ->nullable(false)
                ->change();
        });

        Schema::table('item_categories', function (Blueprint $table) {
            $table
                ->unsignedBigInteger('company_id')
                ->nullable(false)
                ->change();
        });

        Schema::table('items', function (Blueprint $table) {
            $table
                ->unsignedBigInteger('company_id')
                ->nullable(false)
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table
                ->unsignedBigInteger('company_id')
                ->nullable()
                ->change();
        });

        Schema::table('item_categories', function (Blueprint $table) {
            $table
                ->unsignedBigInteger('company_id')
                ->nullable()
                ->change();
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table
                ->unsignedBigInteger('company_id')
                ->nullable()
                ->change();
        });

        Schema::table('account_groups', function (Blueprint $table) {
            $table
                ->unsignedBigInteger('company_id')
                ->nullable()
                ->change();
        });
    }
};