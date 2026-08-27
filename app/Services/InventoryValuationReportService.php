<?php

namespace App\Services;

use App\DTO\CurrentStockFilterDTO;
use App\DTO\InventoryValuationFilterDTO;

class InventoryValuationReportService
{
    public function __construct(
        private CurrentStockService $currentStockService
    ) {}

    public function report(
        InventoryValuationFilterDTO $dto
    ): array {

        $currentStock =
            $this->currentStockService
                ->report(
                    new CurrentStockFilterDTO(
                        warehouseId:
                            $dto->warehouseId,

                        itemId:
                            $dto->itemId,

                        asOfDate:
                            $dto->asOfDate,
                    )
                );

        $rows =
            collect(
                $currentStock['rows']
            )
                ->map(function (array $row) {

                    return [
                        'warehouse_id' =>
                            $row['warehouse_id'],

                        'warehouse_code' =>
                            $row['warehouse_code'],

                        'warehouse_name' =>
                            $row['warehouse_name'],

                        'item_id' =>
                            $row['item_id'],

                        'item_code' =>
                            $row['item_code'],

                        'item_name' =>
                            $row['item_name'],

                        'qty_on_hand' =>
                            $row['qty_on_hand'],

                        'average_cost' =>
                            $row['average_cost'],

                        'inventory_value' =>
                            $row['inventory_value'],

                        'last_transaction_date' =>
                            $row[
                                'last_transaction_date'
                            ],
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
                $rows,

            'total_items' =>
                $rows->count(),

            'total_qty' =>
                round(
                    (float)
                    $rows->sum('qty_on_hand'),
                    4
                ),

            'total_inventory_value' =>
                round(
                    (float)
                    $rows->sum(
                        'inventory_value'
                    ),
                    2
                ),
        ];
    }
}