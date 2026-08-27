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
        return DB::transaction(function () use ($dto) {

            /*
            |--------------------------------------------------------------------------
            | VALIDASI ARAH TRANSAKSI
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
            | LOCK ITEM
            |--------------------------------------------------------------------------
            |
            | Kita lock row item selama proses costing.
            | Ini membantu mencegah dua transaksi inventory bersamaan
            | menggunakan average cost yang sama.
            |--------------------------------------------------------------------------
            */

            $item =
                Item::lockForUpdate()
                    ->findOrFail(
                        $dto->itemId
                    );

            /*
            |--------------------------------------------------------------------------
            | STOCK IN
            |--------------------------------------------------------------------------
            */

            if ($dto->qtyIn > 0) {

                $costing =
                    $this->costingService
                        ->calculateInbound(

                            warehouseId :
                                $dto->warehouseId,

                            itemId :
                                $dto->itemId,

                            qtyIn :
                                $dto->qtyIn,

                            unitCost :
                                $dto->unitCost
                        );

                $newAverageCost =
                    $costing[
                        'new_average_cost'
                    ];
            }

            /*
            |--------------------------------------------------------------------------
            | STOCK OUT
            |--------------------------------------------------------------------------
            */

            else {

                $costing =
                    $this->costingService
                        ->calculateOutbound(

                            warehouseId :
                                $dto->warehouseId,

                            itemId :
                                $dto->itemId,

                            qtyOut :
                                $dto->qtyOut
                        );

                $newAverageCost =
                    $costing[
                        'new_average_cost'
                    ];
            }

            /*
            |--------------------------------------------------------------------------
            | CREATE STOCK LEDGER
            |--------------------------------------------------------------------------
            */

            $ledger =
                $this->stockLedgerRepository
                    ->create([

                        'warehouse_id' =>
                            $dto->warehouseId,

                        'item_id' =>
                            $dto->itemId,

                        'transaction_date' =>
                            now(),

                        'reference_type' =>
                            $dto->referenceType,

                        'reference_id' =>
                            $dto->referenceId,

                        'qty_in' =>
                            $dto->qtyIn,

                        'qty_out' =>
                            $dto->qtyOut,

                        'balance_qty' =>
                            $costing['new_qty'],

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

            /*
            |--------------------------------------------------------------------------
            | UPDATE ITEM AVERAGE COST
            |--------------------------------------------------------------------------
            */

            $item->average_cost =
                $newAverageCost;

            $item->save();

            /*
            |--------------------------------------------------------------------------
            | RETURN
            |--------------------------------------------------------------------------
            */

            return $ledger;
        });
    }
}