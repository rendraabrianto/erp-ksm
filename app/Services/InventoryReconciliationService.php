<?php

namespace App\Services;

use App\DTO\InventoryReconciliationFilterDTO;
use App\Models\Item;
use App\Repositories\Contracts\InventoryReconciliationRepositoryInterface;

class InventoryReconciliationService
{
    public function __construct(

        private InventoryReconciliationRepositoryInterface $repository,

        private InventoryCostingService $costingService,

    ) {}

    public function reconcile(
        InventoryReconciliationFilterDTO $dto
    ) {
        /*
        |--------------------------------------------------------------------------
        | ITEM
        |--------------------------------------------------------------------------
        */

        $item =
            Item::findOrFail(
                $dto->itemId
            );

        /*
        |--------------------------------------------------------------------------
        | STOCK LEDGER
        |--------------------------------------------------------------------------
        */

        $stockLedger =
            $this->repository
                ->getStockLedgerState(
                    $dto
                );

        $ledgerQty =
            $stockLedger
                ? (float) $stockLedger->balance_qty
                : 0;

        /*
        |--------------------------------------------------------------------------
        | COSTING ENGINE
        |--------------------------------------------------------------------------
        */

        $costingState =
            $this->costingService
                ->getCurrentState(
                    $dto->warehouseId,
                    $dto->itemId
                );

        /*
        |--------------------------------------------------------------------------
        | GL INVENTORY
        |--------------------------------------------------------------------------
        */

        $gl =
            $this->repository
                ->getGlInventoryBalance(
                    $dto
                );

        $glDebit =
            $gl
                ? (float) $gl->total_debit
                : 0;

        $glCredit =
            $gl
                ? (float) $gl->total_credit
                : 0;

        $glBalance =
            $glDebit
            -
            $glCredit;

        /*
        |--------------------------------------------------------------------------
        | DIFFERENCE
        |--------------------------------------------------------------------------
        */

        $quantityDifference =
            $ledgerQty
            -
            $costingState['qty'];

        $itemCost =
            (float) $item->average_cost;

        $itemValue =
            $ledgerQty
            *
            $itemCost;

        $costingValue =
            (float)
            $costingState['value'];

        $stockVsGlDifference =
            $costingValue
            -
            $glBalance;

        $stockVsItemDifference =
            $costingValue
            -
            $itemValue;

        /*
        |--------------------------------------------------------------------------
        | STATUS
        |--------------------------------------------------------------------------
        */

        $isQuantityBalanced =
            abs($quantityDifference)
            < 0.0001;

        $isItemCostBalanced =
            abs($stockVsItemDifference)
            < 0.01;

        $isGlBalanced =
            abs($stockVsGlDifference)
            < 0.01;

        return [

            'warehouse_id' =>
                $dto->warehouseId,

            'item_id' =>
                $dto->itemId,

            'item_code' =>
                $item->code,

            'item_name' =>
                $item->name,

            /*
            |--------------------------------------------------------------------------
            | STOCK LEDGER
            |--------------------------------------------------------------------------
            */

            'stock_ledger_qty' =>
                $ledgerQty,

            /*
            |--------------------------------------------------------------------------
            | COSTING ENGINE
            |--------------------------------------------------------------------------
            */

            'costing_qty' =>
                $costingState['qty'],

            'costing_average_cost' =>
                $costingState['average_cost'],

            'costing_value' =>
                $costingValue,

            /*
            |--------------------------------------------------------------------------
            | ITEM MASTER
            |--------------------------------------------------------------------------
            */

            'item_average_cost' =>
                $itemCost,

            'item_valuation' =>
                $itemValue,

            /*
            |--------------------------------------------------------------------------
            | GL
            |--------------------------------------------------------------------------
            */

            'gl_debit' =>
                $glDebit,

            'gl_credit' =>
                $glCredit,

            'gl_inventory_balance' =>
                $glBalance,

            /*
            |--------------------------------------------------------------------------
            | DIFFERENCE
            |--------------------------------------------------------------------------
            */

            'quantity_difference' =>
                $quantityDifference,

            'stock_vs_item_difference' =>
                $stockVsItemDifference,

            'stock_vs_gl_difference' =>
                $stockVsGlDifference,

            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */

            'is_quantity_balanced' =>
                $isQuantityBalanced,

            'is_item_cost_balanced' =>
                $isItemCostBalanced,

            'is_gl_balanced' =>
                $isGlBalanced,

            'is_fully_reconciled' =>
                $isQuantityBalanced
                &&
                $isItemCostBalanced
                &&
                $isGlBalanced,
        ];
    }
}