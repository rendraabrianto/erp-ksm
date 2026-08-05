<?php

namespace App\Services;

use App\Repositories\Contracts\DocumentSequenceRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Exception;

class DocumentSequenceService
{
    public function __construct(
        private DocumentSequenceRepositoryInterface $repository
    ) {}

    public function next(string $documentType): string
    {
        return DB::transaction(function () use ($documentType) {

            $sequence = $this->repository
                ->findByType($documentType);

            if (!$sequence) {
                throw new Exception(
                    "Document sequence not found : {$documentType}"
                );
            }

            $sequence->current_number++;
            $sequence->save();

            return sprintf(
                '%s-%s-%s',
                $sequence->prefix,
                now()->format('Ymd'),
                str_pad(
                    $sequence->current_number,
                    $sequence->padding,
                    '0',
                    STR_PAD_LEFT
                )
            );
        });
    }
}