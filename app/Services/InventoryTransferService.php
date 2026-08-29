<?php

namespace App\Services;

use App\DTO\InventoryTransferCreateDTO;
use App\Models\InventoryTransfer;
use App\Models\InventoryTransferDetail;
use App\Models\Item;
use App\Models\StockLedger;
use App\Models\Warehouse;
use App\DTO\InventoryTransactionDTO;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryTransferService
{
    
    public function __construct(
        private DocumentSequenceService $documentSequenceService,
        private CurrentStockService $currentStockService,
        private InventoryCostingService $costingService,
        private InventoryTransactionService $inventoryTransactionService,
    ) {}

    /**
     * Create Inventory Transfer DRAFT.
     *
     * DRAFT tidak boleh mengubah Stock Ledger.
     * Stock dan costing authoritative akan dihitung ulang
     * ketika transfer diposting.
     */
    public function create(
        InventoryTransferCreateDTO $dto
    ): InventoryTransfer {

        return DB::transaction(
            function () use ($dto) {

                /*
                |--------------------------------------------------------------------------
                | Header Validation
                |--------------------------------------------------------------------------
                */

                if (
                    $dto->sourceWarehouseId
                    ===
                    $dto->destinationWarehouseId
                ) {
                    throw new RuntimeException(
                        'Source warehouse and destination warehouse must be different.'
                    );
                }

                if (empty($dto->details)) {
                    throw new RuntimeException(
                        'Inventory transfer must contain at least one item.'
                    );
                }

                Warehouse::query()
                    ->whereKey(
                        $dto->sourceWarehouseId
                    )
                    ->where(
                        'is_active',
                        true
                    )
                    ->firstOrFail();

                Warehouse::query()
                    ->whereKey(
                        $dto->destinationWarehouseId
                    )
                    ->where(
                        'is_active',
                        true
                    )
                    ->firstOrFail();

                /*
                |--------------------------------------------------------------------------
                | Duplicate Item Validation
                |--------------------------------------------------------------------------
                */

                $itemIds =
                    collect($dto->details)
                        ->pluck('item_id')
                        ->map(
                            fn ($itemId) =>
                                (int) $itemId
                        );

                if (
                    $itemIds->count()
                    !==
                    $itemIds->unique()->count()
                ) {
                    throw new RuntimeException(
                        'Duplicate item is not allowed in inventory transfer.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Generate Transfer Number
                |--------------------------------------------------------------------------
                */

                $transferNo =
                    $this
                        ->documentSequenceService
                        ->next('TRF');

                /*
                |--------------------------------------------------------------------------
                | Create Header
                |--------------------------------------------------------------------------
                */

                $transfer =
                    InventoryTransfer::create([
                        'transfer_no' =>
                            $transferNo,

                        'transfer_date' =>
                            $dto->transferDate,

                        'source_warehouse_id' =>
                            $dto->sourceWarehouseId,

                        'destination_warehouse_id' =>
                            $dto->destinationWarehouseId,

                        'status' =>
                            'DRAFT',

                        'remarks' =>
                            $dto->remarks,

                        'created_by' =>
                            $dto->createdBy,
                    ]);

                /*
                |--------------------------------------------------------------------------
                | Create Details
                |--------------------------------------------------------------------------
                */

                foreach (
                    $dto->details
                    as $detail
                ) {

                    $itemId =
                        (int)
                        $detail['item_id'];

                    $qty =
                        (float)
                        $detail['qty'];

                    if ($qty <= 0) {
                        throw new RuntimeException(
                            'Transfer quantity must be greater than zero.'
                        );
                    }

                    $item =
                        Item::query()
                            ->whereKey(
                                $itemId
                            )
                            ->where(
                                'is_active',
                                true
                            )
                            ->firstOrFail();

                    /*
                    |--------------------------------------------------------------------------
                    | Current Source Quantity
                    |--------------------------------------------------------------------------
                    */

                    $sourceQty =
                        $this
                            ->currentStockService
                            ->get(
                                $dto->sourceWarehouseId,
                                $itemId
                            );

                    if ($qty > $sourceQty) {
                        throw new RuntimeException(
                            'Insufficient source warehouse stock.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Draft Cost Snapshot
                    |--------------------------------------------------------------------------
                    |
                    | Kita ambil cost dari ledger terakhir source warehouse.
                    |
                    | Ini BUKAN authoritative posting cost.
                    | Saat POST nanti cost wajib dihitung ulang.
                    |
                    */

                    $lastLedger =
                        StockLedger::query()
                            ->where(
                                'warehouse_id',
                                $dto->sourceWarehouseId
                            )
                            ->where(
                                'item_id',
                                $itemId
                            )
                            ->orderByDesc(
                                'transaction_date'
                            )
                            ->orderByDesc(
                                'id'
                            )
                            ->first();

                    $unitCost =
                        (float) (
                            $lastLedger?->unit_cost
                            ??
                            $item->average_cost
                            ??
                            0
                        );

                    InventoryTransferDetail::create([
                        'inventory_transfer_id' =>
                            $transfer->id,

                        'item_id' =>
                            $itemId,

                        'qty' =>
                            round(
                                $qty,
                                4
                            ),

                        'unit_cost' =>
                            round(
                                $unitCost,
                                2
                            ),

                        'total_cost' =>
                            round(
                                $qty * $unitCost,
                                2
                            ),

                        'remarks' =>
                            $detail['remarks']
                            ?? null,
                    ]);
                }

                return $transfer
                    ->load([
                        'sourceWarehouse',
                        'destinationWarehouse',
                        'details.item',
                        'creator',
                    ]);
            }
        );
    }

    public function post(
        int $transferId,
        int $postedBy
    ): InventoryTransfer {

        return DB::transaction(
            function () use (
                $transferId,
                $postedBy
            ) {

                /*
                |--------------------------------------------------------------------------
                | Lock Transfer
                |--------------------------------------------------------------------------
                */

                $transfer =
                    InventoryTransfer::query()
                        ->with([
                            'details.item',
                        ])
                        ->lockForUpdate()
                        ->findOrFail(
                            $transferId
                        );

                /*
                |--------------------------------------------------------------------------
                | Idempotency
                |--------------------------------------------------------------------------
                */

                if (
                    $transfer->status
                    ===
                    'POSTED'
                ) {

                    return $transfer;
                }

                if (
                    $transfer->status
                    !==
                    'DRAFT'
                ) {

                    throw new \RuntimeException(
                        'Only DRAFT inventory transfer can be posted.'
                    );
                }

                if (
                    $transfer->details->isEmpty()
                ) {

                    throw new \RuntimeException(
                        'Inventory transfer must contain at least one item.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Posting Details
                |--------------------------------------------------------------------------
                */

                foreach (
                    $transfer->details
                    as $detail
                ) {

                    $qty =
                        (float)
                        $detail->qty;

                    /*
                    |--------------------------------------------------------------------------
                    | Recalculate Authoritative Source State
                    |--------------------------------------------------------------------------
                    */

                    $sourceState =
                        $this
                            ->costingService
                            ->getCurrentState(
                                (int)
                                $transfer
                                    ->source_warehouse_id,

                                (int)
                                $detail->item_id,

                                $transfer
                                    ->transfer_date
                                    ->format('Y-m-d')
                            );

                    if (
                        $qty
                        >
                        (float)
                        $sourceState['qty']
                    ) {

                        throw new \RuntimeException(
                            'Insufficient source warehouse stock at posting time.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | SOURCE OUT
                    |--------------------------------------------------------------------------
                    |
                    | Source OUT menentukan authoritative transfer cost.
                    |
                    */

                    $sourceLedger =
                        $this
                            ->inventoryTransactionService
                            ->post(
                                new InventoryTransactionDTO(
                                    warehouseId:
                                        (int)
                                        $transfer
                                            ->source_warehouse_id,

                                    itemId:
                                        (int)
                                        $detail->item_id,

                                    referenceType:
                                        'INVENTORY_TRANSFER',

                                    referenceId:
                                        (int)
                                        $transfer->id,

                                    qtyIn:
                                        0,

                                    qtyOut:
                                        $qty,

                                    unitCost:
                                        0,

                                    remarks:
                                        'Inventory Transfer OUT '
                                        . $transfer
                                            ->transfer_no,

                                    transactionDate:
                                        $transfer
                                            ->transfer_date
                                            ->format('Y-m-d'),
                                )
                            );

                    /*
                    |--------------------------------------------------------------------------
                    | Actual Authoritative Cost
                    |--------------------------------------------------------------------------
                    */

                    $transferUnitCost =
                        (float)
                        $sourceLedger
                            ->unit_cost;

                    $transferTotalCost =
                        (float)
                        $sourceLedger
                            ->total_cost;

                    if (
                        $transferUnitCost
                        <=
                        0
                    ) {

                        throw new \RuntimeException(
                            'Transfer unit cost must be greater than zero.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | DESTINATION IN
                    |--------------------------------------------------------------------------
                    |
                    | HARUS menggunakan cost actual source OUT.
                    |
                    */

                    $destinationLedger =
                        $this
                            ->inventoryTransactionService
                            ->post(new InventoryTransactionDTO(
                                    warehouseId:
                                        (int)
                                        $transfer
                                            ->destination_warehouse_id,

                                    itemId:
                                        (int)
                                        $detail->item_id,

                                    referenceType:
                                        'INVENTORY_TRANSFER',

                                    referenceId:
                                        (int)
                                        $transfer->id,

                                    qtyIn:
                                        $qty,

                                    qtyOut:
                                        0,

                                    unitCost:
                                        $transferUnitCost,

                                    remarks:
                                        'Inventory Transfer IN '
                                        . $transfer
                                            ->transfer_no,

                                    transactionDate:
                                        $transfer
                                            ->transfer_date
                                            ->format('Y-m-d'),
                                )
                            );

                    /*
                    |--------------------------------------------------------------------------
                    | Closed Loop Guard
                    |--------------------------------------------------------------------------
                    */

                    if (
                        round(
                            (float)
                            $destinationLedger
                                ->total_cost,
                            2
                        )
                        !==
                        round(
                            $transferTotalCost,
                            2
                        )
                    ) {

                        throw new \RuntimeException(
                            'Inventory transfer value mismatch between source and destination.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Update Detail with Actual Posting Cost
                    |--------------------------------------------------------------------------
                    */

                    $detail->update([
                        'unit_cost' =>
                            round(
                                $transferUnitCost,
                                2
                            ),

                        'total_cost' =>
                            round(
                                $transferTotalCost,
                                2
                            ),
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Mark POSTED
                |--------------------------------------------------------------------------
                */

                $transfer->update([
                    'status' =>
                        'POSTED',

                    'posted_by' =>
                        $postedBy,

                    'posted_at' =>
                        now(),
                ]);

                return $transfer
                    ->fresh([
                        'sourceWarehouse',
                        'destinationWarehouse',
                        'details.item',
                        'creator',
                        'poster',
                    ]);
            }
        );
    }
}