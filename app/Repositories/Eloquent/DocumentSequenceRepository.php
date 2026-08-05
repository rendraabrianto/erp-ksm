<?php

namespace App\Repositories\Eloquent;

use App\Models\DocumentSequence;
use App\Repositories\Contracts\DocumentSequenceRepositoryInterface;

class DocumentSequenceRepository
implements DocumentSequenceRepositoryInterface
{
    public function findByType(
        string $documentType
    ): ?DocumentSequence
    {
        return DocumentSequence::where(
            'document_type',
            $documentType
        )->first();
    }

    public function save(
        DocumentSequence $sequence
    ): bool
    {
        return $sequence->save();
    }
}