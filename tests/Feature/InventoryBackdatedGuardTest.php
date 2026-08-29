<?php

namespace Tests\Feature;

use App\DTO\InventoryTransactionDTO;
use App\Models\StockLedger;
use App\Services\InventoryTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;

class InventoryBackdatedGuardTest extends TestCase
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

    public function test_transaction_after_latest_inventory_date_is_allowed():
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
                        'BACKDATED_TEST',

                    referenceId:
                        1001,

                    qtyIn:
                        10,

                    qtyOut:
                        0,

                    unitCost:
                        6500,

                    remarks:
                        'Future inventory transaction',

                    transactionDate:
                        '2026-08-08',
                )
            );

        $this->assertSame(
            '2026-08-08',
            $ledger
                ->transaction_date
                ->format('Y-m-d')
        );

        $this->assertDatabaseHas(
            'stock_ledgers',
            [
                'reference_type' =>
                    'BACKDATED_TEST',

                'reference_id' =>
                    1001,
            ]
        );
    }

    public function test_transaction_on_same_date_as_latest_inventory_date_is_allowed():
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
                        'BACKDATED_TEST',

                    referenceId:
                        1002,

                    qtyIn:
                        10,

                    qtyOut:
                        0,

                    unitCost:
                        6500,

                    remarks:
                        'Same date inventory transaction',

                    transactionDate:
                        '2026-08-07',
                )
            );

        $this->assertSame(
            '2026-08-07',
            $ledger
                ->transaction_date
                ->format('Y-m-d')
        );

        $this->assertDatabaseHas(
            'stock_ledgers',
            [
                'reference_type' =>
                    'BACKDATED_TEST',

                'reference_id' =>
                    1002,
            ]
        );
    }

    public function test_transaction_before_latest_inventory_date_is_rejected():
        void
    {
        $beforeCount =
            StockLedger::query()
                ->where(
                    'warehouse_id',
                    $this->data['warehouse_id']
                )
                ->where(
                    'item_id',
                    $this->data['item_id']
                )
                ->count();

        try {

            $this->service->post(
                new InventoryTransactionDTO(
                    warehouseId:
                        $this->data['warehouse_id'],

                    itemId:
                        $this->data['item_id'],

                    referenceType:
                        'BACKDATED_TEST',

                    referenceId:
                        1003,

                    qtyIn:
                        10,

                    qtyOut:
                        0,

                    unitCost:
                        6500,

                    remarks:
                        'Backdated inventory transaction',

                    transactionDate:
                        '2026-08-06',
                )
            );

            $this->fail(
                'Expected backdated inventory transaction to be rejected.'
            );

        } catch (RuntimeException $exception) {

            $this->assertStringContainsString(
                'Backdated inventory transaction is not allowed.',
                $exception->getMessage()
            );

            $this->assertStringContainsString(
                '2026-08-06',
                $exception->getMessage()
            );

            $this->assertStringContainsString(
                '2026-08-07',
                $exception->getMessage()
            );
        }

        $afterCount =
            StockLedger::query()
                ->where(
                    'warehouse_id',
                    $this->data['warehouse_id']
                )
                ->where(
                    'item_id',
                    $this->data['item_id']
                )
                ->count();

        $this->assertSame(
            $beforeCount,
            $afterCount
        );

        $this->assertDatabaseMissing(
            'stock_ledgers',
            [
                'reference_type' =>
                    'BACKDATED_TEST',

                'reference_id' =>
                    1003,
            ]
        );
    }

    public function test_backdated_outbound_transaction_is_also_rejected():
        void
    {
        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Backdated inventory transaction is not allowed.'
        );

        $this->service->post(
            new InventoryTransactionDTO(
                warehouseId:
                    $this->data['warehouse_id'],

                itemId:
                    $this->data['item_id'],

                referenceType:
                    'BACKDATED_TEST',

                referenceId:
                    1004,

                qtyIn:
                    0,

                qtyOut:
                    10,

                unitCost:
                    0,

                remarks:
                    'Backdated outbound',

                transactionDate:
                    '2026-08-01',
            )
        );
    }
}