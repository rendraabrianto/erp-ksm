<?php

namespace App\Services;

use App\Repositories\Contracts\DocumentSequenceRepositoryInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DocumentSequenceService
{
    public function __construct(
        private DocumentSequenceRepositoryInterface $repository
    ) {}

    public function next(
        int $companyId,
        string $documentType
    ): string {
        return DB::transaction(
            function () use (
                $companyId,
                $documentType
            ) {
                $sequence =
                    $this->repository
                        ->findByTypeForUpdate(
                            $companyId,
                            $documentType
                        );

                if (!$sequence) {
                    throw new RuntimeException(
                        "Document sequence not found for company {$companyId}: {$documentType}"
                    );
                }

                if (!$sequence->is_active) {
                    throw new RuntimeException(
                        "Document sequence is inactive for company {$companyId}: {$documentType}"
                    );
                }

                $sequence->current_number++;

                $this->repository->save(
                    $sequence
                );

                return sprintf(
                    '%s-%s-%s',
                    $sequence->prefix,
                    now()->format('Ymd'),
                    str_pad(
                        (string) $sequence->current_number,
                        $sequence->padding,
                        '0',
                        STR_PAD_LEFT
                    )
                );
            }
        );
    }
}