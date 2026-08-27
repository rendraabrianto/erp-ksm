<?php

namespace App\Repositories\Eloquent;

use App\DTO\InventoryHistoricalReconciliationDTO;
use App\Models\DeliveryOrder;
use App\Models\GoodsReceipt;
use App\Models\Journal;
use App\Models\StockLedger;
use App\Repositories\Contracts\InventoryHistoricalReconciliationRepositoryInterface;

class InventoryHistoricalReconciliationRepository
implements InventoryHistoricalReconciliationRepositoryInterface
{
    public function getStockLedgers(
        InventoryHistoricalReconciliationDTO $dto
    ) {
        return StockLedger::query()

            ->where(
                'warehouse_id',
                $dto->warehouseId
            )

            ->where(
                'item_id',
                $dto->itemId
            )

            ->whereBetween(
                'transaction_date',
                [
                    $dto->dateFrom . ' 00:00:00',
                    $dto->dateTo . ' 23:59:59',
                ]
            )

            ->orderBy('id')

            ->get();
    }

    public function getGoodsReceipts(
        InventoryHistoricalReconciliationDTO $dto
    ) {
        return GoodsReceipt::query()

            ->with('details')

            ->whereBetween(
                'receipt_date',
                [
                    $dto->dateFrom,
                    $dto->dateTo,
                ]
            )

            ->whereHas(
                'details',
                function ($q) use ($dto) {

                    $q->where(
                        'item_id',
                        $dto->itemId
                    );
                }
            )

            ->orderBy('id')

            ->get();
    }

    public function getDeliveryOrders(
        InventoryHistoricalReconciliationDTO $dto
    ) {
        return DeliveryOrder::query()

            ->with('details')

            ->whereBetween(
                'delivery_date',
                [
                    $dto->dateFrom,
                    $dto->dateTo,
                ]
            )

            ->whereHas(
                'details',
                function ($q) use ($dto) {

                    $q->where(
                        'item_id',
                        $dto->itemId
                    );
                }
            )

            ->orderBy('id')

            ->get();
    }

    public function getInventoryJournals(
        InventoryHistoricalReconciliationDTO $dto
    ) {
        return Journal::query()

            ->with('details')

            ->whereBetween(
                'journal_date',
                [
                    $dto->dateFrom,
                    $dto->dateTo,
                ]
            )

            ->whereIn(
                'reference_type',
                [
                    'GOODS_RECEIPT',
                    'DELIVERY_ORDER',
                ]
            )

            ->orderBy('id')

            ->get();
    }
}