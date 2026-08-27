<?php

namespace App\DTO;

class PurchaseOrderDTO
{
    /**
     * @param PurchaseOrderLineDTO[] $lines
     */
    public function __construct(
        public ?int $purchaseRequestId,
        public string $supplierName,
        public ?string $remarks,
        public int $createdBy,
        public array $lines,
    ) {}
}