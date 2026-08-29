<?php

namespace App\DTO;

class InventoryTransferCreateDTO
{
    /**
     * @param array<int, array{
     *     item_id:int,
     *     qty:float,
     *     remarks?:?string
     * }> $details
     */
    public function __construct(
        public int $sourceWarehouseId,
        public int $destinationWarehouseId,
        public string $transferDate,
        public ?string $remarks,
        public int $createdBy,
        public array $details,
    ) {}
}