<?php

namespace App\Services;

use App\DTO\InventoryTransactionDTO;
use App\Models\Item;
use App\Repositories\Contracts\StockLedgerRepositoryInterface;
use Illuminate\Support\Facades\DB;

class InventoryTransactionService
{
    public function __construct(
        private StockLedgerRepositoryInterface $stockLedgerRepository,
        private InventoryCostingService $costingService,
    ) {}

    public function post(
        InventoryTransactionDTO $dto
    ) {
        return DB::transaction(
            function () use ($dto) {

                /*
                |--------------------------------------------------------------------------
                | Validate Transaction Direction
                |--------------------------------------------------------------------------
                */

                if (
                    $dto->qtyIn <= 0
                    &&
                    $dto->qtyOut <= 0
                ) {
                    throw new \Exception(
                        'Inventory transaction must have quantity in or quantity out.'
                    );
                }

                if (
                    $dto->qtyIn > 0
                    &&
                    $dto->qtyOut > 0
                ) {
                    throw new \Exception(
                        'Inventory transaction cannot have qty in and qty out simultaneously.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Resolve Transaction Date
                |--------------------------------------------------------------------------
                */

                $transactionDate =
                    $dto->transactionDate
                    ??
                    now()->toDateString();

                /*
                |--------------------------------------------------------------------------
                | Lock Item
                |--------------------------------------------------------------------------
                |
                | Lock item tetap dipertahankan sebagai serialization mutex
                | untuk transaksi inventory item yang sama.
                |
                */

                Item::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $dto->itemId
                    );

                /*
                |--------------------------------------------------------------------------
                | Backdated Transaction Guard
                |--------------------------------------------------------------------------
                |
                | Moving-average inventory saat ini belum mendukung automatic
                | historical recost/rebuild.
                |
                | Karena itu transaksi tidak boleh dimasukkan sebelum tanggal
                | transaksi terakhir untuk kombinasi warehouse + item.
                |
                | Transaksi pada tanggal yang sama tetap diperbolehkan.
                |
                */

                $latestTransactionDate =
                    $this
                        ->costingService
                        ->getLatestTransactionDate(
                            warehouseId:
                                $dto->warehouseId,

                            itemId:
                                $dto->itemId
                        );

                if (
                    $latestTransactionDate !== null
                    &&
                    $transactionDate
                        <
                    $latestTransactionDate
                ) {
                    throw new \RuntimeException(
                        sprintf(
                            'Backdated inventory transaction is not allowed. '
                            . 'Transaction date %s is earlier than latest inventory date %s '
                            . 'for warehouse %d and item %d.',
                            $transactionDate,
                            $latestTransactionDate,
                            $dto->warehouseId,
                            $dto->itemId
                        )
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | STOCK IN
                |--------------------------------------------------------------------------
                */

                if ($dto->qtyIn > 0) {

                    $costing =
                        $this
                            ->costingService
                            ->calculateInbound(
                                warehouseId:
                                    $dto->warehouseId,

                                itemId:
                                    $dto->itemId,

                                qtyIn:
                                    $dto->qtyIn,

                                unitCost:
                                    $dto->unitCost,

                                transactionDate:
                                    $transactionDate
                            );
                }

                /*
                |--------------------------------------------------------------------------
                | STOCK OUT
                |--------------------------------------------------------------------------
                */

                else {

                    $costing =
                        $this
                            ->costingService
                            ->calculateOutbound(
                                warehouseId:
                                    $dto->warehouseId,

                                itemId:
                                    $dto->itemId,

                                qtyOut:
                                    $dto->qtyOut,

                                transactionDate:
                                    $transactionDate
                            );
                }

                /*
                |--------------------------------------------------------------------------
                | Create Stock Ledger
                |--------------------------------------------------------------------------
                */

                $ledger =
                    $this
                        ->stockLedgerRepository
                        ->create([
                            'warehouse_id' =>
                                $dto->warehouseId,

                            'item_id' =>
                                $dto->itemId,

                            'transaction_date' =>
                                $transactionDate,

                            'reference_type' =>
                                $dto->referenceType,

                            'reference_id' =>
                                $dto->referenceId,

                            'qty_in' =>
                                $dto->qtyIn,

                            'qty_out' =>
                                $dto->qtyOut,

                            'balance_qty' =>
                                $costing[
                                    'new_qty'
                                ],

                            'unit_cost' =>
                                $costing[
                                    'transaction_unit_cost'
                                ],

                            'total_cost' =>
                                $costing[
                                    'transaction_total_cost'
                                ],

                            'remarks' =>
                                $dto->remarks,
                        ]);

                return $ledger;
            }
        );
    }
}