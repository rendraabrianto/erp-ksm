<?php

namespace App\DTO;

class JournalEntryDTO
{
    /**
     * @param JournalLineDTO[] $lines
     */
    public function __construct(
        public string $referenceType,
        public ?int $referenceId,
        public string $description,
        public int $createdBy,
        public array $lines,
    ) {}
}