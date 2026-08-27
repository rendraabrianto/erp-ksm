<?php

namespace App\Services;

use App\Models\StockLedger;

class InventoryCostingService
{
    /**
     * Rekonstruksi posisi costing inventory
     * berdasarkan seluruh histori stock ledger.
     *
     * Return:
     * - qty
     * - value
     * - average_cost
     */
    public function getCurrentState(
        int $warehouseId,
        int $itemId
    ): array {

        $ledgers = StockLedger::query()

            ->where(
                'warehouse_id',
                $warehouseId
            )

            ->where(
                'item_id',
                $itemId
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
            |
            | HPP menggunakan current moving average
            | sebelum barang keluar.
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
            | Hindari floating point negatif kecil
            |--------------------------------------------------------------------------
            */

            if (
                abs($value) < 0.000001
            ) {
                $value = 0;
            }

            if (
                abs($qty) < 0.000001
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
                round($qty, 4),

            'value' =>
                round($value, 2),

            'average_cost' =>
                round($averageCost, 2),
        ];
    }

    /**
     * Hitung transaksi barang masuk.
     */
    public function calculateInbound(
        int $warehouseId,
        int $itemId,
        float $qtyIn,
        float $unitCost
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
                $itemId
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
     * Hitung transaksi barang keluar.
     */
    public function calculateOutbound(
        int $warehouseId,
        int $itemId,
        float $qtyOut
    ): array {

        if ($qtyOut <= 0) {

            throw new \Exception(
                'Outbound quantity must be greater than zero.'
            );
        }

        $current =
            $this->getCurrentState(
                $warehouseId,
                $itemId
            );

        if (
            $qtyOut >
            $current['qty']
        ) {

            throw new \Exception(
                'Insufficient stock.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Moving Average sebelum barang keluar
        |--------------------------------------------------------------------------
        */

        $averageCost =
            $current['average_cost'];

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
            abs($newValue) < 0.000001
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