<?php

namespace Tests\Feature;

use App\DTO\InventoryTransactionDTO;
use App\Models\Company;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\StockLedger;
use App\Services\InventoryTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;

class InventoryTransactionCompanyOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    private InventoryTransactionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->data =
            InventoryReconciliationTestData::create();

        $this->service =
            app(
                InventoryTransactionService::class
            );
    }

    public function test_stock_ledger_inherits_company_from_warehouse():
        void
    {
        /*
        |--------------------------------------------------------------------------
        | Arrange
        |--------------------------------------------------------------------------
        */

        $warehouseId =
            $this->data[
                'warehouse_id'
            ];

        $companyId =
            $this->data[
                'company_id'
            ];

        $itemId =
            $this->data[
                'item_id'
            ];

        /*
        |--------------------------------------------------------------------------
        | Act
        |--------------------------------------------------------------------------
        */

        $ledger =
            $this->service->post(
                new InventoryTransactionDTO(
                    warehouseId:
                        $warehouseId,

                    itemId:
                        $itemId,

                    referenceType:
                        'COMPANY_OWNERSHIP_TEST',

                    referenceId:
                        999001,

                    qtyIn:
                        1,

                    qtyOut:
                        0,

                    unitCost:
                        6000,

                    remarks:
                        'Stock ledger company ownership test',

                    transactionDate:
                        '2026-08-08'
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Assert
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $companyId,
            (int) $ledger->company_id
        );

        $this->assertDatabaseHas(
            'stock_ledgers',
            [
                'id' =>
                    $ledger->id,

                'company_id' =>
                    $companyId,

                'warehouse_id' =>
                    $warehouseId,

                'item_id' =>
                    $itemId,

                'reference_type' =>
                    'COMPANY_OWNERSHIP_TEST',

                'reference_id' =>
                    999001,
            ]
        );
    }

    public function test_stock_ledger_belongs_to_company():
        void
    {
        $ledger =
            $this->service->post(
                new InventoryTransactionDTO(
                    warehouseId:
                        $this->data['warehouse_id'],

                    itemId:
                        $this->data['item_id'],

                    referenceType:
                        'COMPANY_RELATION_TEST',

                    referenceId:
                        999002,

                    qtyIn:
                        1,

                    qtyOut:
                        0,

                    unitCost:
                        6000,

                    remarks:
                        'Stock ledger company relation test',

                    transactionDate:
                        '2026-08-08'
                )
            );

        $ledger->load(
            'company'
        );

        $this->assertNotNull(
            $ledger->company
        );

        $this->assertSame(
            $this->data['company_id'],
            (int) $ledger->company->id
        );
    }

    public function test_inventory_transaction_rejects_item_from_another_company():
        void
    {
        /*
        |--------------------------------------------------------------------------
        | Arrange - Company A
        |--------------------------------------------------------------------------
        */

        $companyAId =
            $this->data['company_id'];

        $warehouseAId =
            $this->data['warehouse_id'];

        $itemA =
            Item::query()
                ->findOrFail(
                    $this->data['item_id']
                );

        /*
        |--------------------------------------------------------------------------
        | Arrange - Company B
        |--------------------------------------------------------------------------
        */

        $companyB =
            Company::query()
                ->create([
                    'code' =>
                        'COMP-INV-B',

                    'name' =>
                        'Inventory Company B',

                    'phone' =>
                        null,

                    'email' =>
                        null,

                    'address' =>
                        null,

                    'is_active' =>
                        true,
                ]);

        $categoryB =
            ItemCategory::query()
                ->create([
                    'company_id' =>
                        $companyB->id,

                    'code' =>
                        'CAT-INV-B',

                    'name' =>
                        'Inventory Category B',

                    'description' =>
                        'Cross-company inventory transaction test',

                    'is_active' =>
                        true,

                    'inventory_account_id' =>
                        null,

                    'cogs_account_id' =>
                        null,

                    'sales_account_id' =>
                        null,

                    'adjustment_gain_account_id' =>
                        null,

                    'adjustment_loss_account_id' =>
                        null,
                ]);

        $itemB =
            Item::query()
                ->create([
                    'company_id' =>
                        $companyB->id,

                    'item_category_id' =>
                        $categoryB->id,

                    'uom_id' =>
                        $itemA->uom_id,

                    'code' =>
                        'ITEM-INV-B',

                    'name' =>
                        'Inventory Item Company B',

                    'description' =>
                        'Cross-company inventory transaction item',

                    'minimum_stock' =>
                        0,

                    'maximum_stock' =>
                        0,

                    'average_cost' =>
                        0,

                    'last_purchase_price' =>
                        0,

                    'is_active' =>
                        true,
                ]);

        /*
        |--------------------------------------------------------------------------
        | Snapshot
        |--------------------------------------------------------------------------
        |
        | Tidak boleh ada ledger Warehouse A + Item B sebelum serangan.
        |
        */

        $ledgerCountBefore =
            StockLedger::query()
                ->where(
                    'warehouse_id',
                    $warehouseAId
                )
                ->where(
                    'item_id',
                    $itemB->id
                )
                ->count();

        $this->assertSame(
            0,
            $ledgerCountBefore
        );

        /*
        |--------------------------------------------------------------------------
        | Act
        |--------------------------------------------------------------------------
        */

        try {
            $this->service->post(
                new InventoryTransactionDTO(
                    warehouseId:
                        $warehouseAId,

                    itemId:
                        $itemB->id,

                    referenceType:
                        'CROSS_COMPANY_ITEM_TEST',

                    referenceId:
                        999003,

                    qtyIn:
                        1,

                    qtyOut:
                        0,

                    unitCost:
                        6000,

                    remarks:
                        'Must reject item from another company',

                    transactionDate:
                        '2026-08-08'
                )
            );

            $this->fail(
                'Expected cross-company item transaction to be rejected.'
            );
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'Item does not belong to transaction company.',
                $exception->getMessage()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Assert - No Cross-Company Ledger
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $companyAId,
            (int) $this->data['company_id']
        );

        $this->assertNotSame(
            $companyAId,
            (int) $itemB->company_id
        );

        $this->assertDatabaseMissing(
            'stock_ledgers',
            [
                'warehouse_id' =>
                    $warehouseAId,

                'item_id' =>
                    $itemB->id,

                'reference_type' =>
                    'CROSS_COMPANY_ITEM_TEST',

                'reference_id' =>
                    999003,
            ]
        );

        $this->assertSame(
            0,
            StockLedger::query()
                ->where(
                    'warehouse_id',
                    $warehouseAId
                )
                ->where(
                    'item_id',
                    $itemB->id
                )
                ->count()
        );
    }
}