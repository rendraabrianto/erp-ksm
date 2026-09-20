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
         * Legacy accounting master data was global.
         *
         * Supported migration states:
         *
         * 0 companies:
         * Fresh installation / testing database.
         * There is no legacy accounting data to backfill.
         *
         * 1 company:
         * Legacy single-company installation.
         * Existing accounting masters can be deterministically assigned
         * to that company.
         *
         * > 1 companies:
         * Ownership of legacy global accounting masters is ambiguous,
         * therefore automatic migration is unsafe.
         */
        $companyIds = DB::table('companies')
            ->orderBy('id')
            ->pluck('id');

        if ($companyIds->count() > 1) {
            throw new \RuntimeException(
                'Accounting master company isolation cannot infer legacy ownership when multiple companies exist.'
            );
        }

        $legacyCompanyId =
            $companyIds->count() === 1
                ? (int) $companyIds->first()
                : null;

        /*
         * Step 1:
         * Add nullable company_id first.
         *
         * It intentionally remains nullable until the final H2
         * accounting isolation migration.
         */
        Schema::table('account_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')
                ->nullable()
                ->after('id')
                ->index();
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')
                ->nullable()
                ->after('id')
                ->index();
        });

        /*
         * Step 2:
         * Backfill legacy records only when a deterministic legacy
         * company exists.
         *
         * On a fresh database both master tables are empty here, so
         * there is nothing to backfill.
         */
        if ($legacyCompanyId !== null) {
            DB::table('account_groups')
                ->whereNull('company_id')
                ->update([
                    'company_id' => $legacyCompanyId,
                ]);

            DB::table('accounts')
                ->whereNull('company_id')
                ->update([
                    'company_id' => $legacyCompanyId,
                ]);
        }

        /*
         * Step 3:
         * Validate existing accounting master rows.
         *
         * Fresh databases contain zero rows, therefore these checks
         * naturally pass.
         */
        $nullAccountGroups = DB::table('account_groups')
            ->whereNull('company_id')
            ->count();

        if ($nullAccountGroups > 0) {
            throw new \RuntimeException(
                "Accounting master isolation failed: {$nullAccountGroups} account_groups have NULL company_id."
            );
        }

        $nullAccounts = DB::table('accounts')
            ->whereNull('company_id')
            ->count();

        if ($nullAccounts > 0) {
            throw new \RuntimeException(
                "Accounting master isolation failed: {$nullAccounts} accounts have NULL company_id."
            );
        }

        /*
         * Step 4:
         * Validate Account -> Account Group company ownership.
         */
        $crossCompanyAccounts = DB::table('accounts as a')
            ->join(
                'account_groups as ag',
                'ag.id',
                '=',
                'a.account_group_id'
            )
            ->whereColumn(
                'a.company_id',
                '<>',
                'ag.company_id'
            )
            ->count();

        if ($crossCompanyAccounts > 0) {
            throw new \RuntimeException(
                "Accounting master isolation failed: {$crossCompanyAccounts} accounts belong to an account group from another company."
            );
        }

        /*
         * Step 5:
         * Validate existing company accounting mappings.
         *
         * GRNI, AP and AR must belong to the same company as the mapping.
         */
        foreach ([
            'grni_account_id',
            'ap_account_id',
            'ar_account_id',
        ] as $column) {
            $invalidMappings = DB::table(
                'accounting_account_mappings as aam'
            )
                ->join(
                    'accounts as a',
                    'a.id',
                    '=',
                    "aam.{$column}"
                )
                ->whereColumn(
                    'a.company_id',
                    '<>',
                    'aam.company_id'
                )
                ->count();

            if ($invalidMappings > 0) {
                throw new \RuntimeException(
                    "Accounting master isolation failed: {$invalidMappings} accounting mappings have cross-company {$column}."
                );
            }
        }

        /*
         * Step 6:
         * Validate existing Item Category accounting mappings.
         *
         * Item categories are not company-scoped yet.
         *
         * This validation is only meaningful for a legacy database
         * where a deterministic company exists. On a fresh database
         * there is no legacy ownership to validate.
         */
        if ($legacyCompanyId !== null) {
            foreach ([
                'inventory_account_id',
                'cogs_account_id',
                'sales_account_id',
                'adjustment_gain_account_id',
                'adjustment_loss_account_id',
            ] as $column) {
                $invalidCategoryAccounts = DB::table(
                    'item_categories as ic'
                )
                    ->join(
                        'accounts as a',
                        'a.id',
                        '=',
                        "ic.{$column}"
                    )
                    ->whereNotNull("ic.{$column}")
                    ->where(
                        'a.company_id',
                        '<>',
                        $legacyCompanyId
                    )
                    ->count();

                if ($invalidCategoryAccounts > 0) {
                    throw new \RuntimeException(
                        "Accounting master isolation failed: {$invalidCategoryAccounts} item category mappings have invalid {$column} ownership."
                    );
                }
            }
        }

        /*
         * Step 7:
         * Replace globally unique codes with company-scoped codes.
         */
        Schema::table('account_groups', function (Blueprint $table) {
            $table->dropUnique(
                'account_groups_code_unique'
            );

            $table->unique(
                [
                    'company_id',
                    'code',
                ],
                'account_groups_company_id_code_unique'
            );
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropUnique(
                'accounts_code_unique'
            );

            $table->unique(
                [
                    'company_id',
                    'code',
                ],
                'accounts_company_id_code_unique'
            );
        });

        /*
         * Step 8:
         * Add company foreign keys.
         *
         * RESTRICT is intentional. Accounting master/history must not
         * disappear because a company is deleted.
         */
        Schema::table('account_groups', function (Blueprint $table) {
            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->restrictOnDelete();
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->restrictOnDelete();
        });

        /*
         * company_id deliberately remains nullable in H2.1.
         *
         * Runtime ownership guards and regression tests must be green
         * before NOT NULL is enforced in the final accounting isolation
         * step.
         */
    }

    public function down(): void
    {
        /*
         * Restoring global UNIQUE(code) is only safe when duplicate
         * codes do not exist across companies.
         */
        $duplicateGroupCodes = DB::table('account_groups')
            ->select('code')
            ->groupBy('code')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplicateGroupCodes > 0) {
            throw new \RuntimeException(
                'Cannot rollback accounting master isolation: duplicate account group codes exist across companies.'
            );
        }

        $duplicateAccountCodes = DB::table('accounts')
            ->select('code')
            ->groupBy('code')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplicateAccountCodes > 0) {
            throw new \RuntimeException(
                'Cannot rollback accounting master isolation: duplicate account codes exist across companies.'
            );
        }

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropForeign([
                'company_id',
            ]);

            $table->dropUnique(
                'accounts_company_id_code_unique'
            );

            $table->unique(
                'code',
                'accounts_code_unique'
            );

            $table->dropColumn(
                'company_id'
            );
        });

        Schema::table('account_groups', function (Blueprint $table) {
            $table->dropForeign([
                'company_id',
            ]);

            $table->dropUnique(
                'account_groups_company_id_code_unique'
            );

            $table->unique(
                'code',
                'account_groups_code_unique'
            );

            $table->dropColumn(
                'company_id'
            );
        });
    }
};