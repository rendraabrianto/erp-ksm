<?php

namespace App\Services;

use App\DTO\CurrentStockFilterDTO;
use App\Models\StockLedger;
use App\Repositories\Contracts\CurrentStockRepositoryInterface;

class CurrentStockService
{
    public function __construct(
        private CurrentStockRepositoryInterface $repository,
        private InventoryMovingAverageCalculator $calculator,
    ) {}

    /**
     * Mengambil saldo quantity terkini.
     *
     * Dipertahankan untuk backward compatibility
     * dengan Inventory Engine yang sudah ada.
     */
    public function get(
        int $warehouseId,
        int $itemId
    ): float {

        return (float) (
            StockLedger::query()
                ->where(
                    'warehouse_id',
                    $warehouseId
                )
                ->where(
                    'item_id',
                    $itemId
                )
                ->latest('id')
                ->value('balance_qty')
            ?? 0
        );
    }

    /**
     * Current Stock Report.
     *
     * Digunakan untuk UI, reporting,
     * filtering dan historical as-of date.
     */
    public function report(
        CurrentStockFilterDTO $dto
    ): array {

        $ledgers =
            $this->repository
                ->getCurrentStocks($dto);

        $stocks =
            $ledgers
                ->groupBy(
                    fn ($ledger) =>
                        $ledger->warehouse_id
                        . ':'
                        . $ledger->item_id
                )
                ->map(function ($rows) {

                    $calculation =
                        $this->calculator
                            ->calculate($rows);

                    $balanceQty =
                        $calculation['final_qty'];

                    $inventoryValue =
                        $calculation[
                            'final_inventory_value'
                        ];

                    $averageCost =
                        $calculation[
                            'final_average_cost'
                        ];

                    $last =
                        $rows->last();

                    $minimumStock =
                        (float) (
                            $last->item
                                ?->minimum_stock
                            ?? 0
                        );

                    $maximumStock =
                        (float) (
                            $last->item
                                ?->maximum_stock
                            ?? 0
                        );

                    return [
                        'warehouse_id' =>
                            (int)
                            $last->warehouse_id,

                        'warehouse_code' =>
                            $last->warehouse?->code,

                        'warehouse_name' =>
                            $last->warehouse?->name,

                        'item_id' =>
                            (int)
                            $last->item_id,

                        'item_code' =>
                            $last->item?->code,

                        'item_name' =>
                            $last->item?->name,

                        'minimum_stock' =>
                            $minimumStock,

                        'maximum_stock' =>
                            $maximumStock,

                        'qty_on_hand' =>
                            round(
                                $balanceQty,
                                4
                            ),

                        'average_cost' =>
                            round(
                                $averageCost,
                                2
                            ),

                        'inventory_value' =>
                            round(
                                $inventoryValue,
                                2
                            ),

                        'last_transaction_date' =>
                            $last->transaction_date,

                        'last_stock_ledger_id' =>
                            (int)
                            $last->id,

                        'is_below_minimum' =>
                            $balanceQty
                            <
                            $minimumStock,
                    ];
                })
                ->values();

        return [
            'warehouse_id' =>
                $dto->warehouseId,

            'item_id' =>
                $dto->itemId,

            'as_of_date' =>
                $dto->asOfDate,

            'rows' =>
                $stocks,

            'total_items' =>
                $stocks->count(),

            'total_inventory_value' =>
                round(
                    (float)
                    $stocks->sum(
                        'inventory_value'
                    ),
                    2
                ),

            'below_minimum_count' =>
                $stocks
                    ->where(
                        'is_below_minimum',
                        true
                    )
                    ->count(),
        ];
    }
}