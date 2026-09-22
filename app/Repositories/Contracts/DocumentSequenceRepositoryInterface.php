<?php

namespace App\Repositories\Contracts;

use App\Models\DocumentSequence;

interface DocumentSequenceRepositoryInterface
{
    public function findByTypeForUpdate(
        int $companyId,
        string $documentType
    ): ?DocumentSequence;

    public function save(
        DocumentSequence $sequence
    ): bool;
}