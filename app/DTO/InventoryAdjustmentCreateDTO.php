<?php

namespace App\DTO;

class InventoryAdjustmentCreateDTO
{
    /**
     * @param array<int, array{
     *     item_id:int,
     *     physical_qty:float,
     *     remarks?:?string
     * }> $details
     */
    public function __construct(
        public int $warehouseId,
        public string $adjustmentDate,
        public string $reason,
        public ?string $remarks,
        public int $createdBy,
        public array $details,
    ) {}
}