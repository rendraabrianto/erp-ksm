<?php

namespace App\Services;

use App\Models\StockLedger;
use Illuminate\Support\Collection;

class StockLedgerService
{
    public function __construct(
        private InventoryMovingAverageCalculator $calculator
    ) {}

    public function getLedger(
        ?int $warehouseId = null,
        ?int $itemId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): Collection {

        $query =
            StockLedger::query()
                ->with([
                    'warehouse',
                    'item',
                ]);

        if ($warehouseId) {
            $query->where(
                'warehouse_id',
                $warehouseId
            );
        }

        if ($itemId) {
            $query->where(
                'item_id',
                $itemId
            );
        }

        /*
        |--------------------------------------------------------------------------
        | IMPORTANT
        |--------------------------------------------------------------------------
        |
        | Untuk menghitung saldo as-of / moving average,
        | kita tetap harus mengambil histori sebelum dateFrom.
        |
        */

        if ($dateTo) {
            $query->whereDate(
                'transaction_date',
                '<=',
                $dateTo
            );
        }

        $allRows =
            $query
                ->orderBy(
                    'warehouse_id'
                )
                ->orderBy(
                    'item_id'
                )
                ->orderBy(
                    'transaction_date'
                )
                ->orderBy('id')
                ->get();

        $result =
            collect();

        $groups =
            $allRows->groupBy(
                fn ($ledger) =>
                    $ledger->warehouse_id
                    . ':'
                    . $ledger->item_id
            );

        foreach ($groups as $rows) {

            $calculation =
                $this->calculator
                    ->calculate($rows);

            foreach (
                $calculation['rows']
                as $calculated
            ) {

                $ledger =
                    $calculated['ledger'];

                if (
                    $dateFrom
                    &&
                    $ledger->transaction_date
                        < $dateFrom
                ) {
                    continue;
                }

                $result->push([
                    'ledger' =>
                        $ledger,

                    'average_cost_before' =>
                        $calculated[
                            'average_cost_before'
                        ],

                    'calculated_outbound_cost' =>
                        $calculated[
                            'calculated_outbound_cost'
                        ],

                    'calculated_balance_qty' =>
                        $calculated[
                            'calculated_balance_qty'
                        ],

                    'calculated_average_cost' =>
                        $calculated[
                            'calculated_average_cost'
                        ],

                    'calculated_inventory_value' =>
                        $calculated[
                            'calculated_inventory_value'
                        ],
                ]);
            }
        }

        return $result;
    }
}