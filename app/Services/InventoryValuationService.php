<?php

namespace App\Services;

use App\Models\Item;

class InventoryValuationService
{
    public function recalculate(
        int $itemId,
        float $qtyReceived,
        float $unitCost
    ): void {

        $item = Item::findOrFail(
            $itemId
        );

        /*
        |--------------------------------------------------------------------------
        | Qty sebelum transaksi
        |--------------------------------------------------------------------------
        */

        $currentQty =
            $this->currentQty($itemId)
            - $qtyReceived;

        $oldValue =
            $currentQty
            *
            $item->average_cost;

        $newValue =
            $qtyReceived
            *
            $unitCost;

        $newQty =
            $currentQty
            +
            $qtyReceived;

        if ($newQty <= 0) {
            return;
        }

        $averageCost =
            (
                $oldValue
                +
                $newValue
            )
            /
            $newQty;

        $item->update([
            'average_cost'        => round($averageCost, 2),
            'last_purchase_price' => $unitCost,
        ]);
    }

    private function currentQty(
        int $itemId
    ): float {

        return \App\Models\StockLedger
            ::where(
                'item_id',
                $itemId
            )
            ->sum(
                \Illuminate\Support\Facades\DB::raw(
                    'qty_in - qty_out'
                )
            );
    }
}