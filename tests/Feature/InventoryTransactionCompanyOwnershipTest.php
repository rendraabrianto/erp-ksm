<?php

namespace Tests\Feature;

use App\DTO\InventoryTransactionDTO;
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
}