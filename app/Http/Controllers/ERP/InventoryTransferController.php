<?php

namespace App\Http\Controllers\ERP;

use App\DTO\InventoryTransferCreateDTO;
use App\Http\Controllers\Controller;
use App\Models\InventoryTransfer;
use App\Models\Item;
use App\Models\StockLedger;
use App\Models\Warehouse;
use App\Services\InventoryTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryTransferController extends Controller
{
    public function __construct(
        private InventoryTransferService $service
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
            InventoryTransfer::query()
                ->with([
                    'sourceWarehouse',
                    'destinationWarehouse',
                    'creator',
                    'poster',
                ])
                ->latest('transfer_date')
                ->latest('id');

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->string('status')
            );
        }

        if ($request->filled('source_warehouse_id')) {
            $query->where(
                'source_warehouse_id',
                $request->integer(
                    'source_warehouse_id'
                )
            );
        }

        if ($request->filled('destination_warehouse_id')) {
            $query->where(
                'destination_warehouse_id',
                $request->integer(
                    'destination_warehouse_id'
                )
            );
        }

        $transfers =
            $query->paginate(25)
                ->withQueryString();

        $warehouses =
            Warehouse::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

        return view(
            'erp.inventory.transfer.index',
            compact(
                'transfers',
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
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

        $items =
            Item::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

        return view(
            'erp.inventory.transfer.create',
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
                'source_warehouse_id' => [
                    'required',
                    'integer',
                    'exists:warehouses,id',
                    'different:destination_warehouse_id',
                ],

                'destination_warehouse_id' => [
                    'required',
                    'integer',
                    'exists:warehouses,id',
                    'different:source_warehouse_id',
                ],

                'transfer_date' => [
                    'required',
                    'date',
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
                    'distinct',
                ],

                'details.*.qty' => [
                    'required',
                    'numeric',
                    'gt:0',
                ],

                'details.*.remarks' => [
                    'nullable',
                    'string',
                    'max:500',
                ],
            ]);

        $transfer =
            $this->service->create(
                new InventoryTransferCreateDTO(
                    sourceWarehouseId:
                        (int)
                        $validated['source_warehouse_id'],

                    destinationWarehouseId:
                        (int)
                        $validated['destination_warehouse_id'],

                    transferDate:
                        $validated['transfer_date'],

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
                'erp.inventory.transfer.show',
                $transfer
            )
            ->with(
                'success',
                'Inventory transfer draft berhasil dibuat.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */

    public function show(
        InventoryTransfer $transfer
    ): View {

        $transfer->load([
            'sourceWarehouse',
            'destinationWarehouse',
            'creator',
            'poster',
            'details.item',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Related Stock Ledgers
        |--------------------------------------------------------------------------
        |
        | Transfer tidak membuat P&L / adjustment journal.
        | Traceability utama adalah dua sisi stock ledger:
        |
        | SOURCE      -> OUT
        | DESTINATION -> IN
        |
        */

        $stockLedgers =
            StockLedger::query()
                ->with([
                    'warehouse',
                    'item',
                ])
                ->where(
                    'reference_type',
                    'INVENTORY_TRANSFER'
                )
                ->where(
                    'reference_id',
                    $transfer->id
                )
                ->orderBy('transaction_date')
                ->orderBy('id')
                ->get();

        return view(
            'erp.inventory.transfer.show',
            [
                'transfer' =>
                    $transfer,

                'stockLedgers' =>
                    $stockLedgers,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | POST
    |--------------------------------------------------------------------------
    */

    public function post(
        InventoryTransfer $transfer
    ): RedirectResponse {

        try {

            $posted =
                $this->service->post(
                    (int) $transfer->id,
                    (int) auth()->id()
                );

            return redirect()
                ->route(
                    'erp.inventory.transfer.show',
                    $posted
                )
                ->with(
                    'success',
                    'Inventory transfer berhasil diposting.'
                );

        } catch (\Throwable $exception) {

            report($exception);

            return redirect()
                ->route(
                    'erp.inventory.transfer.show',
                    $transfer
                )
                ->with(
                    'error',
                    $exception->getMessage()
                );
        }
    }
}