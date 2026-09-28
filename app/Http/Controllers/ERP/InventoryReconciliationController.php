<?php

namespace App\Http\Controllers\ERP;

use App\DTO\InventoryHistoricalReconciliationDTO;
use App\DTO\InventoryReconciliationAdjustmentDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryReconciliationRequest;
use App\Models\Account;
use App\Models\Item;
use App\Models\Warehouse;
use App\Services\InventoryHistoricalReconciliationService;
use App\Services\InventoryReconciliationAdjustmentService;
use Illuminate\Http\Request;
use App\Services\InventoryReconciliationHistoryService;
use App\Models\InventoryReconciliationHistory;
use App\Services\AccountingAccountResolverService;

class InventoryReconciliationController extends Controller
{
    public function __construct(
        private InventoryHistoricalReconciliationService $historicalService,
        private InventoryReconciliationAdjustmentService $adjustmentService,
        private InventoryReconciliationHistoryService $historyService,
        private AccountingAccountResolverService $accountResolver,
    ) {}

    public function index(
        Request $request
    ) {
        $companyId = (int) $request->user()->company_id;

        $warehouses = Warehouse::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $items = Item::query()
            ->with('category')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view(
            'erp.inventory.reconciliation.index',
            compact(
                'warehouses',
                'items'
            )
        );
    }

    public function reconcile(
        InventoryReconciliationRequest $request
    ) {
        $companyId = (int) $request->user()->company_id;
        $item = Item::query()
            ->with('category')
            ->where('company_id', $companyId)
            ->findOrFail(
                $request->integer('item_id')
            );

        if (! $item->category) {
            return back()
                ->withInput()
                ->with(
                    'error',
                    'Kategori item tidak ditemukan.'
                );
        }

        if (! $item->category->inventory_account_id) {
            return back()
                ->withInput()
                ->with(
                    'error',
                    'Inventory account belum dimapping pada kategori item.'
                );
        }

        $dto =
            new InventoryHistoricalReconciliationDTO(
                companyId:
                    $companyId,

                warehouseId:
                    $request->integer('warehouse_id'),

                itemId:
                    $item->id,

                dateFrom:
                    $request->string('date_from')->toString(),

                dateTo:
                    $request->string('date_to')->toString(),

                inventoryAccountId:
                    (int)
                    $item->category->inventory_account_id,
            );

        $result =
            $this->historicalService
                ->reconcile($dto);

        $warehouses = Warehouse::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $items = Item::query()
            ->with('category')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view(
            'erp.inventory.reconciliation.index',
            [
                'warehouses' => $warehouses,
                'items' => $items,
                'result' => $result,
                'selectedWarehouseId' =>
                    $request->integer('warehouse_id'),
                'selectedItemId' =>
                    $item->id,
                'dateFrom' =>
                    $request->string('date_from')->toString(),
                'dateTo' =>
                    $request->string('date_to')->toString(),
            ]
        );
    }

    public function preview(
        InventoryReconciliationRequest $request
    ) {
        $companyId = (int) $request->user()->company_id;
        $item = Item::query()
            ->with('category')
            ->where('company_id', $companyId)
            ->findOrFail(
                $request->integer('item_id')
            );

        if (
            ! $item->category
            ||
            ! $item->category->inventory_account_id
            ||
            ! $item->category->cogs_account_id
        ) {
            return back()
                ->withInput()
                ->with(
                    'error',
                    'Mapping Inventory/HPP account pada kategori item belum lengkap.'
                );
        }

        $warehouse =
            Warehouse::query()
                ->where('company_id', $companyId)
                ->findOrFail(
                    $request->integer('warehouse_id')
                );

        $grni =
            $this->accountResolver
                ->grni(
                    $companyId
                );

        $dto =
            new InventoryReconciliationAdjustmentDTO(
                companyId:
                    $companyId,

                warehouseId:
                    $request->integer('warehouse_id'),

                itemId:
                    $item->id,

                dateFrom:
                    $request->string('date_from')->toString(),

                dateTo:
                    $request->string('date_to')->toString(),

                inventoryAccountId:
                    (int)
                    $item->category->inventory_account_id,

                cogsAccountId:
                    (int)
                    $item->category->cogs_account_id,

                grniAccountId:
                    (int)
                    $grni->id,
            );

        $preview =
            $this->adjustmentService
                ->preview($dto);

        $accountIds = collect(
            $preview['proposals']
        )
            ->flatMap(
                fn ($proposal) =>
                    collect(
                        $proposal['lines']
                    )->pluck('account_id')
            )
            ->unique()
            ->values();

        $accounts = Account::query()
            ->where(
                'company_id',
                $companyId
            )
            ->whereIn(
                'id',
                $accountIds
            )
            ->get()
            ->keyBy('id');

        return view(
            'erp.inventory.reconciliation.preview',
            [
                'preview' => $preview,
                'item' => $item,

                'warehouse' => $warehouse,

                'dateFrom' =>
                    $request->string('date_from')->toString(),

                'dateTo' =>
                    $request->string('date_to')->toString(),

                'accounts' =>
                    $accounts,
            ]
        );
    }

    public function apply(
        InventoryReconciliationRequest $request
    ) {
        $companyId = (int) $request->user()->company_id;
        $item = Item::query()
            ->with('category')
            ->where('company_id', $companyId)
            ->findOrFail(
                $request->integer('item_id')
            );

        if (
            ! $item->category
            ||
            ! $item->category->inventory_account_id
            ||
            ! $item->category->cogs_account_id
        ) {
            return back()
                ->withInput()
                ->with(
                    'error',
                    'Mapping Inventory/HPP account pada kategori item belum lengkap.'
                );
        }

        $warehouse =
            Warehouse::query()
                ->where('company_id', $companyId)
                ->findOrFail(
                    $request->integer('warehouse_id')
                );

        $grni =
            $this->accountResolver
                ->grni(
                    $companyId
                );

        $dto = new InventoryReconciliationAdjustmentDTO(
            companyId:
                $companyId,

            warehouseId:
                $request->integer('warehouse_id'),

            itemId:
                $item->id,

            dateFrom:
                $request->string('date_from')->toString(),

            dateTo:
                $request->string('date_to')->toString(),

            inventoryAccountId:
                (int)
                $item->category->inventory_account_id,

            cogsAccountId:
                (int)
                $item->category->cogs_account_id,

            grniAccountId:
                (int)
                $grni->id,
        );

        $result =
            $this->historyService
                ->applyAndRecord(
                    $dto,
                    (int) $request->user()->id
                );

        return redirect()
        ->route(
            'erp.inventory.reconciliation.index'
        )
        ->with(
            'success',
            $result['apply']['status']
                === 'POSTED'
                    ? $result['apply']['posted_count']
                        . ' reconciliation journal berhasil diposting dan history tersimpan.'
                    : 'Tidak ada adjustment yang perlu diposting.'
        );
    }

    public function history(
    Request $request
    ) {
        $companyId =
            (int) $request->user()->company_id;

        $histories =
            InventoryReconciliationHistory::query()
                ->where(
                    'company_id',
                    $companyId
                )
                ->with([
                    'warehouse',
                    'item',
                    'executor',
                ])
                ->latest('executed_at')
                ->paginate(20);

        return view(
            'erp.inventory.reconciliation.history',
            compact('histories')
        );
    }

    public function historyDetail(
        Request $request,
        int $history
    ) {
        $companyId =
            (int) $request->user()->company_id;

        $history =
            InventoryReconciliationHistory::query()
                ->where(
                    'company_id',
                    $companyId
                )
                ->with([
                    'warehouse',
                    'item',
                    'executor',
                ])
                ->findOrFail(
                    $history
                );

        return view(
            'erp.inventory.reconciliation.history-detail',
            compact('history')
        );
    }
}