<?php

namespace App\Repositories\Eloquent;

use App\Models\DocumentSequence;
use App\Repositories\Contracts\DocumentSequenceRepositoryInterface;

class DocumentSequenceRepository
implements DocumentSequenceRepositoryInterface
{
    public function findByTypeForUpdate(
        int $companyId,
        string $documentType
    ): ?DocumentSequence {
        return DocumentSequence::query()
            ->where(
                'company_id',
                $companyId
            )
            ->where(
                'document_type',
                $documentType
            )
            ->lockForUpdate()
            ->first();
    }

    public function save(
        DocumentSequence $sequence
    ): bool {
        return $sequence->save();
    }
}