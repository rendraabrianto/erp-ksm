<?php

namespace Tests\Feature;

use App\DTO\FinanceDashboardFilterDTO;
use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\Company;
use App\Models\Item;
use App\Models\StockLedger;
use App\Repositories\Contracts\FinanceDashboardRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinanceDashboardRepositoryCompanyIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;
    private Company $companyB;

    private int $cashAccountA;
    private int $cashAccountB;

    private int $bankAccountA;
    private int $bankAccountB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::query()->create([
            'code' => 'FD-A',
            'name' => 'Finance Dashboard Company A',
            'is_active' => true,
        ]);

        $this->companyB = Company::query()->create([
            'code' => 'FD-B',
            'name' => 'Finance Dashboard Company B',
            'is_active' => true,
        ]);

        $groupA = AccountGroup::query()->create([
            'company_id' => $this->companyA->id,
            'code' => 'FD-A-ASSET',
            'name' => 'Asset A',
            'type' => 'ASSET',
            'normal_balance' => 'DEBIT',
        ]);

        $groupB = AccountGroup::query()->create([
            'company_id' => $this->companyB->id,
            'code' => 'FD-B-ASSET',
            'name' => 'Asset B',
            'type' => 'ASSET',
            'normal_balance' => 'DEBIT',
        ]);

        $this->cashAccountA = Account::query()->create([
            'company_id' => $this->companyA->id,
            'account_group_id' => $groupA->id,
            'code' => '1001',
            'name' => 'Cash A',
            'normal_balance' => 'DEBIT',
            'is_header' => false,
            'is_active' => true,
        ])->id;

        $this->cashAccountB = Account::query()->create([
            'company_id' => $this->companyB->id,
            'account_group_id' => $groupB->id,
            'code' => '1001',
            'name' => 'Cash B',
            'normal_balance' => 'DEBIT',
            'is_header' => false,
            'is_active' => true,
        ])->id;

        $this->bankAccountA = Account::query()->create([
            'company_id' => $this->companyA->id,
            'account_group_id' => $groupA->id,
            'code' => '1002',
            'name' => 'Bank A',
            'normal_balance' => 'DEBIT',
            'is_header' => false,
            'is_active' => true,
        ])->id;

        $this->bankAccountB = Account::query()->create([
            'company_id' => $this->companyB->id,
            'account_group_id' => $groupB->id,
            'code' => '1002',
            'name' => 'Bank B',
            'normal_balance' => 'DEBIT',
            'is_header' => false,
            'is_active' => true,
        ])->id;

        $this->createJournal(
            $this->companyA->id,
            'FD-JV-A',
            [
                [$this->cashAccountA, 1000, 0],
                [$this->bankAccountA, 0, 300],
            ]
        );

        $this->createJournal(
            $this->companyB->id,
            'FD-JV-B',
            [
                [$this->cashAccountB, 9000, 0],
                [$this->bankAccountB, 0, 7000],
            ]
        );
    }

    public function test_cash_position_is_isolated_by_company(): void
    {
        $repository =
            app(FinanceDashboardRepositoryInterface::class);

        $dto =
            new FinanceDashboardFilterDTO(
                dateFrom: '2026-09-01',
                dateTo: '2026-09-30',
            );

        $rowsA =
            $repository->getCashPosition(
                $this->companyA->id,
                $dto
            );

        $rowsB =
            $repository->getCashPosition(
                $this->companyB->id,
                $dto
            );

        $idsA =
            collect($rowsA)
                ->pluck('account_id');

        $idsB =
            collect($rowsB)
                ->pluck('account_id');

        $this->assertTrue(
            $idsA->contains($this->cashAccountA)
        );

        $this->assertTrue(
            $idsA->contains($this->bankAccountA)
        );

        $this->assertFalse(
            $idsA->contains($this->cashAccountB)
        );

        $this->assertFalse(
            $idsA->contains($this->bankAccountB)
        );

        $this->assertTrue(
            $idsB->contains($this->cashAccountB)
        );

        $this->assertTrue(
            $idsB->contains($this->bankAccountB)
        );

        $this->assertFalse(
            $idsB->contains($this->cashAccountA)
        );

        $this->assertFalse(
            $idsB->contains($this->bankAccountA)
        );

        $this->assertEquals(
            700.0,
            (float) collect($rowsA)->sum('balance')
        );

        $this->assertEquals(
            2000.0,
            (float) collect($rowsB)->sum('balance')
        );
    }

    public function test_negative_bank_accounts_are_isolated_by_company(): void
    {
        $repository =
            app(FinanceDashboardRepositoryInterface::class);

        $dto =
            new FinanceDashboardFilterDTO(
                dateFrom: '2026-09-01',
                dateTo: '2026-09-30',
            );

        $rowsA =
            collect(
                $repository->getBankNegativeAccounts(
                    $this->companyA->id,
                    $dto
                )
            );

        $rowsB =
            collect(
                $repository->getBankNegativeAccounts(
                    $this->companyB->id,
                    $dto
                )
            );

        $this->assertCount(1, $rowsA);
        $this->assertCount(1, $rowsB);

        $this->assertSame(
            'Bank A',
            $rowsA->first()['name']
        );

        $this->assertEquals(
            -300.0,
            (float) $rowsA->first()['balance']
        );

        $this->assertSame(
            'Bank B',
            $rowsB->first()['name']
        );

        $this->assertEquals(
            -7000.0,
            (float) $rowsB->first()['balance']
        );
    }

    public function test_low_stock_items_are_isolated_by_company(): void
    {
        $fixtureA =
            \Tests\Support\InventoryReconciliationTestData::create();

        /*
        * FixtureA membuat company sendiri.
        *
        * Untuk test low-stock ini kita gunakan company fixture tersebut
        * sebagai tenant yang sedang dibaca repository.
        */
        $companyAId =
            $fixtureA['company_id'];

        $warehouseAId =
            $fixtureA['warehouse_id'];

        $itemAId =
            $fixtureA['item_id'];

        /*
        * Pastikan threshold lebih tinggi daripada saldo terakhir fixture.
        *
        * Historical fixture berakhir dengan qty 173.
        */
        DB::table('items')
            ->where('id', $itemAId)
            ->update([
                'minimum_stock' => 200,
            ]);

        /*
        * --------------------------------------------------------------
        * Company B
        * --------------------------------------------------------------
        *
        * Gunakan companyB yang sudah dibuat oleh setUp().
        */
        $branchBId =
            DB::table('branches')
                ->insertGetId([
                    'company_id' => $this->companyB->id,
                    'code' => 'FD-B-BR',
                    'name' => 'Finance Dashboard Branch B',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

        $warehouseBId =
            DB::table('warehouses')
                ->insertGetId([
                    'company_id' => $this->companyB->id,
                    'branch_id' => $branchBId,
                    'code' => 'FD-B-WH',
                    'name' => 'Finance Dashboard Warehouse B',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

        $uomId =
            DB::table('uoms')
                ->insertGetId([
                    'code' => 'FD-UOM',
                    'name' => 'Finance Dashboard UOM',
                    'symbol' => 'UNIT',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

        $categoryBId =
            DB::table('item_categories')
                ->insertGetId([
                    'company_id' => $this->companyB->id,
                    'code' => 'FD-B-CAT',
                    'name' => 'Finance Dashboard Category B',
                    'inventory_account_id' => $this->cashAccountB,
                    'cogs_account_id' => null,
                    'sales_account_id' => null,
                    'adjustment_gain_account_id' => null,
                    'adjustment_loss_account_id' => null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

        $itemBId =
            DB::table('items')
                ->insertGetId([
                    'company_id' => $this->companyB->id,
                    'item_category_id' => $categoryBId,
                    'uom_id' => $uomId,
                    'code' => 'FD-B-ITEM',
                    'name' => 'Finance Dashboard Item B',
                    'minimum_stock' => 1000,
                    'average_cost' => 100,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

        DB::table('stock_ledgers')
            ->insert([
                'company_id' => $this->companyB->id,
                'warehouse_id' => $warehouseBId,
                'item_id' => $itemBId,
                'transaction_date' => '2026-09-20',
                'reference_type' => 'OPENING',
                'reference_id' => 99001,
                'qty_in' => 10,
                'qty_out' => 0,
                'balance_qty' => 10,
                'unit_cost' => 100,
                'total_cost' => 1000,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        /*
        * --------------------------------------------------------------
        * Execute
        * --------------------------------------------------------------
        */
        $repository =
            app(FinanceDashboardRepositoryInterface::class);

        $rowsA =
            collect(
                $repository->getLowStockItems(
                    $companyAId
                )
            );

        $rowsB =
            collect(
                $repository->getLowStockItems(
                    $this->companyB->id
                )
            );

        /*
        * --------------------------------------------------------------
        * Company A
        * --------------------------------------------------------------
        */
        $this->assertTrue(
            $rowsA->contains(
                fn ($row) =>
                    (int) $row['item_id'] === $itemAId
                    &&
                    (int) $row['warehouse_id'] === $warehouseAId
            )
        );

        $this->assertFalse(
            $rowsA->contains(
                fn ($row) =>
                    (int) $row['item_id'] === $itemBId
            )
        );

        /*
        * --------------------------------------------------------------
        * Company B
        * --------------------------------------------------------------
        */
        $this->assertTrue(
            $rowsB->contains(
                fn ($row) =>
                    (int) $row['item_id'] === $itemBId
                    &&
                    (int) $row['warehouse_id'] === $warehouseBId
            )
        );

        $this->assertFalse(
            $rowsB->contains(
                fn ($row) =>
                    (int) $row['item_id'] === $itemAId
            )
        );
    }

    private function createJournal(
        int $companyId,
        string $journalNo,
        array $details
    ): void {
        $journalId =
            DB::table('journals')
                ->insertGetId([
                    'company_id' => $companyId,
                    'journal_date' => '2026-09-10',
                    'journal_no' => $journalNo,
                    'reference_type' => 'FINANCE_DASHBOARD_TEST',
                    'reference_id' => $companyId,
                    'description' => 'Finance Dashboard Isolation Test',
                    'created_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

        foreach ($details as [
            $accountId,
            $debit,
            $credit
        ]) {
            DB::table('journal_details')
                ->insert([
                    'journal_id' => $journalId,
                    'account_id' => $accountId,
                    'debit' => $debit,
                    'credit' => $credit,
                    'description' => 'Finance Dashboard Isolation Test',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        }
    }
}