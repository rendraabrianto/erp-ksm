<?php

namespace App\Http\Controllers\ERP;

use App\DTO\CurrentStockFilterDTO;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Warehouse;
use App\Services\CurrentStockService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CurrentStockController extends Controller
{
    public function __construct(
        private CurrentStockService $currentStockService
    ) {}

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

                'as_of_date' => [
                    'nullable',
                    'date',
                ],
            ]);

        $dto =
            new CurrentStockFilterDTO(
                warehouseId:
                    isset($validated['warehouse_id'])
                        ? (int) $validated['warehouse_id']
                        : null,

                itemId:
                    isset($validated['item_id'])
                        ? (int) $validated['item_id']
                        : null,

                asOfDate:
                    $validated['as_of_date']
                    ?? null,
            );

        $result =
            $this->currentStockService
                ->report($dto);

        $warehouses =
            Warehouse::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

        $items =
            Item::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

        return view(
            'erp.inventory.current-stock.index',
            compact(
                'result',
                'warehouses',
                'items'
            )
        );
    }
}