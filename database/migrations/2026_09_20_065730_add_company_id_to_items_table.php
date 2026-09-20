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
        | 1. Add nullable company ownership
        |--------------------------------------------------------------------------
        |
        | company_id intentionally remains nullable during Phase H2.5.
        | NOT NULL enforcement will be performed later after runtime guards
        | and cross-company tests are complete.
        |
        */

        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->nullable()
                ->after('id')
                ->index();
        });

        /*
        |--------------------------------------------------------------------------
        | 2. Deterministic Backfill From Item Category
        |--------------------------------------------------------------------------
        |
        | Item category is already company-owned, therefore it is the
        | authoritative ownership source for existing items.
        |
        */

        DB::statement("
            UPDATE items i
            INNER JOIN item_categories ic
                ON ic.id = i.item_category_id
            SET i.company_id = ic.company_id
            WHERE i.company_id IS NULL
              AND ic.company_id IS NOT NULL
        ");

        /*
        |--------------------------------------------------------------------------
        | 3. Validate Backfill
        |--------------------------------------------------------------------------
        */

        $itemsWithoutCompany =
            DB::table('items')
                ->whereNull('company_id')
                ->count();

        if ($itemsWithoutCompany > 0) {
            throw new \RuntimeException(
                "Cannot determine company ownership for {$itemsWithoutCompany} item(s)."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Validate Item ↔ Category Company Ownership
        |--------------------------------------------------------------------------
        */

        $categoryCompanyMismatch =
            DB::table('items as i')
                ->join(
                    'item_categories as ic',
                    'ic.id',
                    '=',
                    'i.item_category_id'
                )
                ->whereColumn(
                    'i.company_id',
                    '<>',
                    'ic.company_id'
                )
                ->count();

        if ($categoryCompanyMismatch > 0) {
            throw new \RuntimeException(
                "Found {$categoryCompanyMismatch} item(s) whose company does not match the item category company."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Add Company Foreign Key
        |--------------------------------------------------------------------------
        */

        Schema::table('items', function (Blueprint $table) {
            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->restrictOnDelete();
        });

        /*
        |--------------------------------------------------------------------------
        | 6. Replace Global Item Code Uniqueness
        |--------------------------------------------------------------------------
        |
        | OLD:
        |     UNIQUE(code)
        |
        | NEW:
        |     UNIQUE(company_id, code)
        |
        | This allows:
        |
        | Company A → ITEM001
        | Company B → ITEM001
        |
        */

        Schema::table('items', function (Blueprint $table) {
            $table->dropUnique(
                'items_code_unique'
            );

            $table->unique(
                [
                    'company_id',
                    'code',
                ],
                'items_company_id_code_unique'
            );
        });
    }

    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Safety Check Before Restoring Global UNIQUE(code)
        |--------------------------------------------------------------------------
        |
        | Rolling back would make item codes globally unique again.
        | Refuse rollback if multiple companies already use the same code.
        |
        */

        $duplicateCode =
            DB::table('items')
                ->select(
                    'code',
                    DB::raw('COUNT(*) as total')
                )
                ->whereNull('deleted_at')
                ->groupBy('code')
                ->havingRaw('COUNT(*) > 1')
                ->first();

        if ($duplicateCode !== null) {
            throw new \RuntimeException(
                "Cannot rollback item company isolation because item code '{$duplicateCode->code}' exists in multiple records."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Restore Global Code Uniqueness
        |--------------------------------------------------------------------------
        */

        Schema::table('items', function (Blueprint $table) {
            $table->dropUnique(
                'items_company_id_code_unique'
            );

            $table->unique(
                'code',
                'items_code_unique'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Remove Company Ownership
        |--------------------------------------------------------------------------
        */

        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(
                ['company_id']
            );

            $table->dropIndex(
                ['company_id']
            );

            $table->dropColumn(
                'company_id'
            );
        });
    }
};