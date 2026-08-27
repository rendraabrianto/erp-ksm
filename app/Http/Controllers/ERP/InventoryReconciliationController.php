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

class InventoryReconciliationController extends Controller
{
    public function __construct(
    private InventoryHistoricalReconciliationService $historicalService,
    private InventoryReconciliationAdjustmentService $adjustmentService,
    private InventoryReconciliationHistoryService $historyService,
) {}

    public function index()
    {
        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $items = Item::query()
            ->with('category')
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
        $item = Item::query()
            ->with('category')
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
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $items = Item::query()
            ->with('category')
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
        $item = Item::query()
            ->with('category')
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

        $grni = Account::query()
            ->where('code', '2101')
            ->where('is_active', true)
            ->first();

        if (! $grni) {
            return back()
                ->withInput()
                ->with(
                    'error',
                    'Account GRNI 2101 tidak ditemukan atau tidak aktif.'
                );
        }

        $dto =
            new InventoryReconciliationAdjustmentDTO(
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

                'warehouse' =>
                    Warehouse::findOrFail(
                        $request->integer('warehouse_id')
                    ),

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
        $item = Item::query()
            ->with('category')
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

        $grni = Account::query()
            ->where('code', '2101')
            ->where('is_active', true)
            ->first();

        if (! $grni) {
            return back()
                ->withInput()
                ->with(
                    'error',
                    'Account GRNI 2101 tidak ditemukan atau tidak aktif.'
                );
        }

        $dto = new InventoryReconciliationAdjustmentDTO(
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
                    auth()->id()
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

    public function history()
    {
        $histories =
            InventoryReconciliationHistory::query()
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
    InventoryReconciliationHistory $history
    ) {
        $history->load([
            'warehouse',
            'item',
            'executor',
        ]);

        return view(
            'erp.inventory.reconciliation.history-detail',
            compact('history')
        );
    }
}