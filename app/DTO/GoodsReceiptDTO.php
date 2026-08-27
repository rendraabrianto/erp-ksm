<?php

namespace App\DTO;

class GoodsReceiptDTO
{
    /**
     * @param GoodsReceiptLineDTO[] $lines
     */
    public function __construct(
        public int $purchaseOrderId,
        public string $supplierName,
        public ?string $remarks,
        public int $warehouseId,
        public int $createdBy,
        public array $lines,
    ) {}
}