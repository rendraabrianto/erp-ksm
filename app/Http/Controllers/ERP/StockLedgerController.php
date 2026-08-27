<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Warehouse;
use App\Services\StockLedgerService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockLedgerController extends Controller
{
    public function __construct(
        private readonly StockLedgerService $service
    ) {
    }

    public function index(
        Request $request
    ): View {

        $validated =
            $request->validate([
                'warehouse_id' => [
                    'nullable',
                    'integer',
                    'exists:warehouses,id',
                ],

                'item_id' => [
                    'nullable',
                    'integer',
                    'exists:items,id',
                ],

                'date_from' => [
                    'nullable',
                    'date',
                ],

                'date_to' => [
                    'nullable',
                    'date',
                    'after_or_equal:date_from',
                ],
            ]);

        $ledgers =
            $this->service->getLedger(
                warehouseId:
                    isset($validated['warehouse_id'])
                        ? (int) $validated['warehouse_id']
                        : null,

                itemId:
                    isset($validated['item_id'])
                        ? (int) $validated['item_id']
                        : null,

                dateFrom:
                    $validated['date_from']
                    ?? null,

                dateTo:
                    $validated['date_to']
                    ?? null,
            );

        $warehouses =
            Warehouse::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get();

        $items =
            Item::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get();

        return view(
            'erp.inventory.stock-ledger.index',
            compact(
                'ledgers',
                'warehouses',
                'items'
            )
        );
    }
}