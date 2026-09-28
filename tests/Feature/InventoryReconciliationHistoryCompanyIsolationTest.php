<?php

namespace Tests\Feature;

use App\DTO\InventoryReconciliationAdjustmentDTO;
use App\Models\Branch;
use App\Models\Company;
use App\Models\InventoryReconciliationHistory;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Warehouse;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use App\Services\InventoryReconciliationHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;

class InventoryReconciliationHistoryCompanyIsolationTest extends TestCase
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
                companyId:
                    $this->data['company_id'],

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

    public function test_apply_and_record_persists_transaction_company():
        void
    {
        $result =
            app(
                InventoryReconciliationHistoryService::class
            )->applyAndRecord(
                $this->dto,
                $this->data['user_id']
            );

        $history =
            $result['history'];

        $this->assertNotNull(
            $history
        );

        $this->assertSame(
            $this->data['company_id'],
            $history->company_id
        );

        $this->assertDatabaseHas(
            'inventory_reconciliation_histories',
            [
                'id' =>
                    $history->id,

                'company_id' =>
                    $this->data['company_id'],

                'warehouse_id' =>
                    $this->data['warehouse_id'],

                'item_id' =>
                    $this->data['item_id'],
            ]
        );
    }

    public function test_company_scoped_history_query_excludes_other_company():
        void
    {
        $companyAHistory =
            $this->createCompanyAHistory();

        $companyBData =
            $this->createCompanyBHistory();

        $histories =
            InventoryReconciliationHistory::query()
                ->where(
                    'company_id',
                    $this->data['company_id']
                )
                ->get();

        $this->assertTrue(
            $histories->contains(
                'id',
                $companyAHistory->id
            )
        );

        $this->assertFalse(
            $histories->contains(
                'id',
                $companyBData['history']->id
            )
        );

        $this->assertTrue(
            $histories->every(
                fn (
                    InventoryReconciliationHistory $history
                ) =>
                    $history->company_id
                    === $this->data['company_id']
            )
        );
    }

    public function test_company_scoped_detail_query_cannot_read_other_company_history():
        void
    {
        $companyBData =
            $this->createCompanyBHistory();

        $history =
            InventoryReconciliationHistory::query()
                ->where(
                    'company_id',
                    $this->data['company_id']
                )
                ->find(
                    $companyBData['history']->id
                );

        $this->assertNull(
            $history
        );
    }

    public function test_history_company_relation_is_available():
        void
    {
        $history =
            $this->createCompanyAHistory();

        $this->assertNotNull(
            $history->company
        );

        $this->assertSame(
            $this->data['company_id'],
            $history->company->id
        );
    }

    public function test_http_history_index_only_displays_authenticated_company():
        void
    {
        $companyAHistory =
            $this->createCompanyAHistory();

        $companyBData =
            $this->createCompanyBHistory();

        $user =
            $this->companyAUserWithViewPermission();

        $response =
            $this
                ->actingAs($user)
                ->get(
                    route(
                        'erp.inventory.reconciliation.history'
                    )
                );

        $response->assertOk();

        $response->assertViewHas(
            'histories',
            function ($histories) use (
                $companyAHistory,
                $companyBData
            ): bool {
                $ids =
                    collect(
                        $histories->items()
                    )->pluck('id');

                return $ids->contains(
                    $companyAHistory->id
                )
                    && ! $ids->contains(
                        $companyBData['history']->id
                    );
            }
        );
    }

    public function test_http_history_detail_can_read_own_company_history():
        void
    {
        $history =
            $this->createCompanyAHistory();

        $user =
            $this->companyAUserWithViewPermission();

        $response =
            $this
                ->actingAs($user)
                ->get(
                    route(
                        'erp.inventory.reconciliation.history.detail',
                        $history->id
                    )
                );

        $response->assertOk();

        $response->assertViewHas(
            'history',
            function (
                InventoryReconciliationHistory $viewHistory
            ) use ($history): bool {
                return $viewHistory->id
                        === $history->id
                    && $viewHistory->company_id
                        === $this->data['company_id'];
            }
        );
    }

    public function test_http_history_detail_returns_404_for_other_company_history():
        void
    {
        $companyBData =
            $this->createCompanyBHistory();

        $user =
            $this->companyAUserWithViewPermission();

        $response =
            $this
                ->actingAs($user)
                ->get(
                    route(
                        'erp.inventory.reconciliation.history.detail',
                        $companyBData['history']->id
                    )
                );

        $response->assertNotFound();
    }

    private function companyAUserWithViewPermission():
        User
    {
        Permission::firstOrCreate([
            'name' =>
                'inventory.reconciliation.view',

            'guard_name' =>
                'web',
        ]);

        $user =
            User::query()
                ->where(
                    'company_id',
                    $this->data['company_id']
                )
                ->findOrFail(
                    $this->data['user_id']
                );

        $user->givePermissionTo(
            'inventory.reconciliation.view'
        );

        return $user;
    }

    private function createCompanyAHistory():
        InventoryReconciliationHistory
    {
        return InventoryReconciliationHistory::create([
            'company_id' =>
                $this->data['company_id'],

            'warehouse_id' =>
                $this->data['warehouse_id'],

            'item_id' =>
                $this->data['item_id'],

            'date_from' =>
                '2026-08-01',

            'date_to' =>
                '2026-08-31',

            'missing_before' =>
                0,

            'mismatch_before' =>
                0,

            'orphan_before' =>
                0,

            'recovery_posted' =>
                0,

            'correction_posted' =>
                0,

            'reversal_posted' =>
                0,

            'journal_posted_count' =>
                0,

            'missing_after' =>
                0,

            'mismatch_after' =>
                0,

            'orphan_after' =>
                0,

            'is_reconciled_after' =>
                true,

            'executed_by' =>
                $this->data['user_id'],

            'executed_at' =>
                now(),
        ]);
    }

    private function createCompanyBHistory():
        array
    {
        $company =
            Company::create([
                'code' =>
                    'COMP-B-REC-HIST',

                'name' =>
                    'Company B Reconciliation History',

                'is_active' =>
                    true,
            ]);

        $branch =
            Branch::create([
                'company_id' =>
                    $company->id,

                'code' =>
                    'BR-B-REC-HIST',

                'name' =>
                    'Branch B Reconciliation History',

                'is_active' =>
                    true,
            ]);

        $warehouse =
            Warehouse::create([
                'company_id' =>
                    $company->id,

                'branch_id' =>
                    $branch->id,

                'code' =>
                    'WH-REC-HIST',

                'name' =>
                    'Warehouse Company B Reconciliation',

                'is_active' =>
                    true,
            ]);

        $sourceItem =
            Item::query()
                ->where(
                    'company_id',
                    $this->data['company_id']
                )
                ->firstOrFail();

        $category =
            ItemCategory::create([
                'company_id' =>
                    $company->id,

                'code' =>
                    'CAT-B-REC-HIST',

                'name' =>
                    'Category Company B Reconciliation',

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

        $item =
            Item::create([
                'company_id' =>
                    $company->id,

                'item_category_id' =>
                    $category->id,

                'uom_id' =>
                    $sourceItem->uom_id,

                'code' =>
                    'ITEM-B-REC-HIST',

                'name' =>
                    'Item Company B Reconciliation',

                'minimum_stock' =>
                    0,

                'maximum_stock' =>
                    1000,

                'average_cost' =>
                    9000,

                'is_active' =>
                    true,
            ]);

        $history =
            InventoryReconciliationHistory::create([
                'company_id' =>
                    $company->id,

                'warehouse_id' =>
                    $warehouse->id,

                'item_id' =>
                    $item->id,

                'date_from' =>
                    '2026-08-01',

                'date_to' =>
                    '2026-08-31',

                'missing_before' =>
                    0,

                'mismatch_before' =>
                    0,

                'orphan_before' =>
                    0,

                'recovery_posted' =>
                    0,

                'correction_posted' =>
                    0,

                'reversal_posted' =>
                    0,

                'journal_posted_count' =>
                    0,

                'missing_after' =>
                    0,

                'mismatch_after' =>
                    0,

                'orphan_after' =>
                    0,

                'is_reconciled_after' =>
                    true,

                'executed_by' =>
                    null,

                'executed_at' =>
                    now(),
            ]);

        return [
            'company_id' =>
                $company->id,

            'warehouse_id' =>
                $warehouse->id,

            'item_id' =>
                $item->id,

            'history' =>
                $history,
        ];
    }
}