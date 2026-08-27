<?php

namespace App\Services;

use Illuminate\Support\Collection;

class InventoryMovingAverageCalculator
{
    public function calculate(Collection $rows): array
    {
        $balanceQty = 0.0;
        $inventoryValue = 0.0;
        $averageCost = 0.0;

        $calculatedRows = [];

        foreach ($rows as $ledger) {

            $qtyIn =
                (float) $ledger->qty_in;

            $qtyOut =
                (float) $ledger->qty_out;

            $averageBefore =
                $balanceQty > 0
                    ? $inventoryValue / $balanceQty
                    : 0.0;

            /*
            |--------------------------------------------------------------------------
            | INBOUND
            |--------------------------------------------------------------------------
            */

            if ($qtyIn > 0) {

                $unitCost =
                    (float) $ledger->unit_cost;

                $inventoryValue +=
                    $qtyIn * $unitCost;

                $balanceQty +=
                    $qtyIn;
            }

            /*
            |--------------------------------------------------------------------------
            | OUTBOUND
            |--------------------------------------------------------------------------
            */

            $outboundCost = 0.0;

            if ($qtyOut > 0) {

                $outboundCost =
                    $qtyOut * $averageBefore;

                $inventoryValue -=
                    $outboundCost;

                $balanceQty -=
                    $qtyOut;
            }

            /*
            |--------------------------------------------------------------------------
            | MOVING AVERAGE AFTER TRANSACTION
            |--------------------------------------------------------------------------
            */

            $averageCost =
                $balanceQty > 0
                    ? $inventoryValue / $balanceQty
                    : 0.0;

            $calculatedRows[] = [
                'ledger' =>
                    $ledger,

                'average_cost_before' =>
                    round(
                        $averageBefore,
                        2
                    ),

                'calculated_outbound_cost' =>
                    round(
                        $outboundCost,
                        2
                    ),

                'calculated_balance_qty' =>
                    round(
                        $balanceQty,
                        4
                    ),

                'calculated_average_cost' =>
                    round(
                        $averageCost,
                        2
                    ),

                'calculated_inventory_value' =>
                    round(
                        $inventoryValue,
                        2
                    ),
            ];
        }

        return [
            'rows' =>
                collect(
                    $calculatedRows
                ),

            'final_qty' =>
                round(
                    $balanceQty,
                    4
                ),

            'final_average_cost' =>
                round(
                    $averageCost,
                    2
                ),

            'final_inventory_value' =>
                round(
                    $inventoryValue,
                    2
                ),
        ];
    }
}