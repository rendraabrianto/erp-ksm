<?php

namespace App\Http\Controllers\ERP;

use App\DTO\InventoryValuationFilterDTO;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Warehouse;
use App\Services\InventoryValuationReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryValuationController extends Controller
{
    public function __construct(
        private InventoryValuationReportService $service
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
            new InventoryValuationFilterDTO(
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
            $this->service
                ->report($dto);

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
            'erp.inventory.valuation.index',
            compact(
                'result',
                'warehouses',
                'items'
            )
        );
    }
}