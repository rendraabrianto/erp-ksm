<?php

namespace App\Http\Controllers\ERP;

use App\DTO\InventoryAdjustmentCreateDTO;
use App\Http\Controllers\Controller;
use App\Models\InventoryAdjustment;
use App\Models\Item;
use App\Models\Warehouse;
use App\Services\InventoryAdjustmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Models\Journal;

class InventoryAdjustmentController extends Controller
{
    public function __construct(
        private InventoryAdjustmentService $service
    ) {}

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index(
        Request $request
    ): View {

        $query =
            InventoryAdjustment::query()
                ->with([
                    'warehouse',
                    'creator',
                    'poster',
                ])
                ->latest(
                    'adjustment_date'
                )
                ->latest('id');

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->string('status')
            );
        }

        if ($request->filled('warehouse_id')) {
            $query->where(
                'warehouse_id',
                $request->integer(
                    'warehouse_id'
                )
            );
        }

        $adjustments =
            $query->paginate(25)
                ->withQueryString();

        $warehouses =
            Warehouse::query()
                ->where(
                    'is_active',
                    true
                )
                ->orderBy('name')
                ->get();

        return view(
            'erp.inventory.adjustment.index',
            compact(
                'adjustments',
                'warehouses'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */

    public function create(): View
    {
        $warehouses =
            Warehouse::query()
                ->where(
                    'is_active',
                    true
                )
                ->orderBy('name')
                ->get();

        $items =
            Item::query()
                ->where(
                    'is_active',
                    true
                )
                ->orderBy('name')
                ->get();

        return view(
            'erp.inventory.adjustment.create',
            compact(
                'warehouses',
                'items'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | STORE DRAFT
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request
    ): RedirectResponse {

        $validated =
            $request->validate([
                'warehouse_id' => [
                    'required',
                    'integer',
                    'exists:warehouses,id',
                ],

                'adjustment_date' => [
                    'required',
                    'date',
                ],

                'reason' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'remarks' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],

                'details' => [
                    'required',
                    'array',
                    'min:1',
                ],

                'details.*.item_id' => [
                    'required',
                    'integer',
                    'exists:items,id',
                ],

                'details.*.physical_qty' => [
                    'required',
                    'numeric',
                    'min:0',
                ],

                'details.*.remarks' => [
                    'nullable',
                    'string',
                    'max:500',
                ],
            ]);

        $adjustment =
            $this->service->create(
                new InventoryAdjustmentCreateDTO(
                    warehouseId:
                        (int)
                        $validated['warehouse_id'],

                    adjustmentDate:
                        $validated[
                            'adjustment_date'
                        ],

                    reason:
                        $validated['reason'],

                    remarks:
                        $validated['remarks']
                        ?? null,

                    createdBy:
                        (int)
                        auth()->id(),

                    details:
                        $validated['details'],
                )
            );

        return redirect()
            ->route(
                'erp.inventory.adjustment.show',
                $adjustment
            )
            ->with(
                'success',
                'Inventory adjustment draft berhasil dibuat.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */

    public function show(
        InventoryAdjustment $adjustment
    ): View {

        /*
        |--------------------------------------------------------------------------
        | Load Adjustment Relations
        |--------------------------------------------------------------------------
        */

        $adjustment->load([
            'warehouse',
            'creator',
            'poster',
            'details.item',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Find Related Journal
        |--------------------------------------------------------------------------
        */

        $journal =
            Journal::query()
                ->where(
                    'reference_type',
                    'INVENTORY_ADJUSTMENT'
                )
                ->where(
                    'reference_id',
                    $adjustment->id
                )
                ->first();

        /*
        |--------------------------------------------------------------------------
        | View
        |--------------------------------------------------------------------------
        */

        return view(
            'erp.inventory.adjustment.show',
            [
                'adjustment' =>
                    $adjustment,

                'journal' =>
                    $journal,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | POST
    |--------------------------------------------------------------------------
    */

    public function post(
        InventoryAdjustment $adjustment
    ): RedirectResponse {

        try {

            $posted =
                $this->service->post(
                    (int) $adjustment->id,
                    (int) auth()->id()
                );

            return redirect()
                ->route(
                    'erp.inventory.adjustment.show',
                    $posted
                )
                ->with(
                    'success',
                    'Inventory adjustment berhasil diposting.'
                );

        } catch (\Throwable $exception) {

            report($exception);

            return redirect()
                ->route(
                    'erp.inventory.adjustment.show',
                    $adjustment
                )
                ->with(
                    'error',
                    $exception->getMessage()
                );
        }
    }
}