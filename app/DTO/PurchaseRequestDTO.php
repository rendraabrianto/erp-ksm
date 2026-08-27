<?php

namespace App\DTO;

class PurchaseRequestDTO
{
    /**
     * @param PurchaseRequestLineDTO[] $lines
     */
    public function __construct(
        public int $warehouseId,
        public ?string $remarks,
        public int $createdBy,
        public array $lines,
    ) {}
}