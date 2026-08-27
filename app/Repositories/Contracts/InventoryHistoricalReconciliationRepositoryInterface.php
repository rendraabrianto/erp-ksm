<?php

namespace App\Repositories\Contracts;

use App\DTO\InventoryHistoricalReconciliationDTO;

interface InventoryHistoricalReconciliationRepositoryInterface
{
    public function getStockLedgers(
        InventoryHistoricalReconciliationDTO $dto
    );

    public function getGoodsReceipts(
        InventoryHistoricalReconciliationDTO $dto
    );

    public function getDeliveryOrders(
        InventoryHistoricalReconciliationDTO $dto
    );

    public function getInventoryJournals(
        InventoryHistoricalReconciliationDTO $dto
    );
}