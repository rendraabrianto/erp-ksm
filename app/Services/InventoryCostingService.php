<?php

namespace App\Services;

use App\Models\StockLedger;

class InventoryCostingService
{
        /**
     * Get latest inventory transaction date
     * for a warehouse-item combination.
     */
    public function getLatestTransactionDate(
        int $warehouseId,
        int $itemId
    ): ?string {

        $latestDate =
            StockLedger::query()
                ->where(
                    'warehouse_id',
                    $warehouseId
                )
                ->where(
                    'item_id',
                    $itemId
                )
                ->max(
                    'transaction_date'
                );

        return $latestDate
            ? (string) $latestDate
            : null;
    }

    /**
     * Rekonstruksi posisi costing inventory.
     *
     * Jika $asOfDate diisi:
     * hanya ledger <= tanggal tersebut yang dihitung.
     */
    public function getCurrentState(
        int $warehouseId,
        int $itemId,
        ?string $asOfDate = null
    ): array {

        $query =
            StockLedger::query()
                ->where(
                    'warehouse_id',
                    $warehouseId
                )
                ->where(
                    'item_id',
                    $itemId
                );

        /*
        |--------------------------------------------------------------------------
        | Historical As Of
        |--------------------------------------------------------------------------
        */

        if ($asOfDate !== null) {

            $query->whereDate(
                'transaction_date',
                '<=',
                $asOfDate
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Business Transaction Ordering
        |--------------------------------------------------------------------------
        |
        | Jangan hanya berdasarkan ID.
        | Tanggal transaksi adalah urutan bisnis utama.
        |
        */

        $ledgers =
            $query
                ->orderBy(
                    'transaction_date'
                )
                ->orderBy('id')
                ->get([
                    'qty_in',
                    'qty_out',
                    'unit_cost',
                ]);

        $qty = 0.0;

        $value = 0.0;

        foreach ($ledgers as $ledger) {

            $qtyIn =
                (float) $ledger->qty_in;

            $qtyOut =
                (float) $ledger->qty_out;

            $unitCost =
                (float) $ledger->unit_cost;

            /*
            |--------------------------------------------------------------------------
            | STOCK IN
            |--------------------------------------------------------------------------
            */

            if ($qtyIn > 0) {

                if ($unitCost <= 0) {

                    throw new \Exception(
                        'Inbound inventory must have a valid unit cost.'
                    );
                }

                $value +=
                    $qtyIn
                    *
                    $unitCost;

                $qty +=
                    $qtyIn;
            }

            /*
            |--------------------------------------------------------------------------
            | STOCK OUT
            |--------------------------------------------------------------------------
            */

            if ($qtyOut > 0) {

                if ($qtyOut > $qty) {

                    throw new \Exception(
                        'Inventory costing detected insufficient historical stock.'
                    );
                }

                $averageCost =
                    $qty > 0
                        ? $value / $qty
                        : 0;

                $outValue =
                    $qtyOut
                    *
                    $averageCost;

                $value -=
                    $outValue;

                $qty -=
                    $qtyOut;
            }

            /*
            |--------------------------------------------------------------------------
            | Floating Point Protection
            |--------------------------------------------------------------------------
            */

            if (
                abs($value)
                <
                0.000001
            ) {
                $value = 0;
            }

            if (
                abs($qty)
                <
                0.000001
            ) {
                $qty = 0;
            }
        }

        $averageCost =
            $qty > 0
                ? $value / $qty
                : 0;

        return [
            'qty' =>
                round(
                    $qty,
                    4
                ),

            'value' =>
                round(
                    $value,
                    2
                ),

            'average_cost' =>
                round(
                    $averageCost,
                    2
                ),
        ];
    }

    /**
     * Calculate inbound inventory transaction.
     */
    public function calculateInbound(
        int $warehouseId,
        int $itemId,
        float $qtyIn,
        float $unitCost,
        ?string $transactionDate = null
    ): array {

        if ($qtyIn <= 0) {

            throw new \Exception(
                'Inbound quantity must be greater than zero.'
            );
        }

        if ($unitCost <= 0) {

            throw new \Exception(
                'Inbound unit cost must be greater than zero.'
            );
        }

        $current =
            $this->getCurrentState(
                $warehouseId,
                $itemId,
                $transactionDate
            );

        $inValue =
            $qtyIn
            *
            $unitCost;

        $newQty =
            $current['qty']
            +
            $qtyIn;

        $newValue =
            $current['value']
            +
            $inValue;

        $newAverageCost =
            $newQty > 0
                ? $newValue / $newQty
                : 0;

        return [
            'old_qty' =>
                $current['qty'],

            'old_value' =>
                $current['value'],

            'old_average_cost' =>
                $current['average_cost'],

            'transaction_qty' =>
                $qtyIn,

            'transaction_unit_cost' =>
                $unitCost,

            'transaction_total_cost' =>
                $inValue,

            'new_qty' =>
                $newQty,

            'new_value' =>
                $newValue,

            'new_average_cost' =>
                $newAverageCost,
        ];
    }

    /**
     * Calculate outbound inventory transaction.
     */
    public function calculateOutbound(
        int $warehouseId,
        int $itemId,
        float $qtyOut,
        ?string $transactionDate = null
    ): array {

        if ($qtyOut <= 0) {

            throw new \Exception(
                'Outbound quantity must be greater than zero.'
            );
        }

        $current =
            $this->getCurrentState(
                $warehouseId,
                $itemId,
                $transactionDate
            );

        if (
            $qtyOut
            >
            $current['qty']
        ) {

            throw new \Exception(
                'Insufficient stock.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Moving Average Before OUT
        |--------------------------------------------------------------------------
        */

        $averageCost =
            $current[
                'average_cost'
            ];

        $outValue =
            $qtyOut
            *
            $averageCost;

        $newQty =
            $current['qty']
            -
            $qtyOut;

        $newValue =
            $current['value']
            -
            $outValue;

        if (
            abs($newValue)
            <
            0.000001
        ) {
            $newValue = 0;
        }

        $newAverageCost =
            $newQty > 0
                ? $newValue / $newQty
                : 0;

        return [
            'old_qty' =>
                $current['qty'],

            'old_value' =>
                $current['value'],

            'old_average_cost' =>
                $averageCost,

            'transaction_qty' =>
                $qtyOut,

            'transaction_unit_cost' =>
                $averageCost,

            'transaction_total_cost' =>
                $outValue,

            'new_qty' =>
                $newQty,

            'new_value' =>
                $newValue,

            'new_average_cost' =>
                $newAverageCost,
        ];
    }
}