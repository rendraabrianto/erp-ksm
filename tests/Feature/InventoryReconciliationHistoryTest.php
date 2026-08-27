<?php

namespace Tests\Feature;

use App\DTO\InventoryReconciliationAdjustmentDTO;
use App\Models\InventoryReconciliationHistory;
use App\Models\Journal;
use App\Services\InventoryReconciliationHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;

class InventoryReconciliationHistoryTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    private InventoryReconciliationAdjustmentDTO $dto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->data =
            InventoryReconciliationTestData::create();

        $this->dto =
            new InventoryReconciliationAdjustmentDTO(
                warehouseId:
                    $this->data['warehouse_id'],

                itemId:
                    $this->data['item_id'],

                dateFrom:
                    '2026-08-01',

                dateTo:
                    '2026-08-31',

                inventoryAccountId:
                    $this->data['inventory_account_id'],

                cogsAccountId:
                    $this->data['cogs_account_id'],

                grniAccountId:
                    $this->data['grni_account_id'],
            );
    }

    private function service():
        InventoryReconciliationHistoryService
    {
        return app(
            InventoryReconciliationHistoryService::class
        );
    }

    public function test_apply_and_record_creates_one_reconciliation_history():
        void
    {
        $result =
            $this->service()
                ->applyAndRecord(
                    $this->dto,
                    $this->data['user_id']
                );

        $this->assertSame(
            'POSTED',
            $result['apply']['status']
        );

        $this->assertSame(
            8,
            $result['apply']['posted_count']
        );

        $this->assertNotNull(
            $result['history']
        );

        $this->assertSame(
            1,
            InventoryReconciliationHistory::count()
        );
    }

    public function test_history_records_before_state_correctly():
        void
    {
        $result =
            $this->service()
                ->applyAndRecord(
                    $this->dto,
                    $this->data['user_id']
                );

        $history =
            $result['history'];

        $this->assertSame(
            3,
            $history->missing_before
        );

        $this->assertSame(
            2,
            $history->mismatch_before
        );

        $this->assertSame(
            3,
            $history->orphan_before
        );
    }

    public function test_history_records_posted_journal_counts_correctly():
        void
    {
        $result =
            $this->service()
                ->applyAndRecord(
                    $this->dto,
                    $this->data['user_id']
                );

        $history =
            $result['history'];

        $this->assertSame(
            3,
            $history->recovery_posted
        );

        $this->assertSame(
            2,
            $history->correction_posted
        );

        $this->assertSame(
            3,
            $history->reversal_posted
        );

        $this->assertSame(
            8,
            $history->journal_posted_count
        );
    }

    public function test_history_records_after_state_as_fully_reconciled():
        void
    {
        $result =
            $this->service()
                ->applyAndRecord(
                    $this->dto,
                    $this->data['user_id']
                );

        $history =
            $result['history'];

        $this->assertSame(
            0,
            $history->missing_after
        );

        $this->assertSame(
            0,
            $history->mismatch_after
        );

        $this->assertSame(
            0,
            $history->orphan_after
        );

        $this->assertTrue(
            $history->is_reconciled_after
        );
    }

    public function test_history_records_scope_and_executor():
        void
    {
        $result =
            $this->service()
                ->applyAndRecord(
                    $this->dto,
                    $this->data['user_id']
                );

        $history =
            $result['history'];

        $this->assertSame(
            $this->data['warehouse_id'],
            $history->warehouse_id
        );

        $this->assertSame(
            $this->data['item_id'],
            $history->item_id
        );

        $this->assertSame(
            $this->data['user_id'],
            $history->executed_by
        );

        $this->assertSame(
            '2026-08-01',
            $history->date_from
                ->format('Y-m-d')
        );

        $this->assertSame(
            '2026-08-31',
            $history->date_to
                ->format('Y-m-d')
        );

        $this->assertNotNull(
            $history->executed_at
        );
    }

    public function test_second_apply_does_not_create_empty_history():
        void
    {
        $service =
            $this->service();

        $first =
            $service->applyAndRecord(
                $this->dto,
                $this->data['user_id']
            );

        $this->assertSame(
            'POSTED',
            $first['apply']['status']
        );

        $this->assertSame(
            1,
            InventoryReconciliationHistory::count()
        );

        $journalCountBefore =
            Journal::count();

        $second =
            $service->applyAndRecord(
                $this->dto,
                $this->data['user_id']
            );

        $this->assertSame(
            'NOTHING_TO_POST',
            $second['apply']['status']
        );

        $this->assertNull(
            $second['history']
        );

        $this->assertSame(
            1,
            InventoryReconciliationHistory::count()
        );

        $this->assertSame(
            $journalCountBefore,
            Journal::count()
        );
    }

    public function test_history_relations_are_available():
        void
    {
        $result =
            $this->service()
                ->applyAndRecord(
                    $this->dto,
                    $this->data['user_id']
                );

        $history =
            $result['history'];

        $this->assertNotNull(
            $history->warehouse
        );

        $this->assertNotNull(
            $history->item
        );

        $this->assertNotNull(
            $history->executor
        );

        $this->assertSame(
            $this->data['warehouse_id'],
            $history->warehouse->id
        );

        $this->assertSame(
            $this->data['item_id'],
            $history->item->id
        );

        $this->assertSame(
            $this->data['user_id'],
            $history->executor->id
        );
    }
}