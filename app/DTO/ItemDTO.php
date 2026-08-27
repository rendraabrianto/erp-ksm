<?php

namespace App\DTO;

class ItemDTO
{
    public function __construct(
        public int $itemCategoryId,
        public int $uomId,
        public string $code,
        public string $name,
        public ?string $description,
        public float $minimumStock,
        public float $maximumStock,
        public bool $isActive = true,
    ) {}
}