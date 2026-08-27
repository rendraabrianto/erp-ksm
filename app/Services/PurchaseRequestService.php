<?php

namespace App\Services;

use App\DTO\PurchaseRequestDTO;
use App\Models\PurchaseRequestDetail;
use App\Repositories\Contracts\PurchaseRequestRepositoryInterface;
use Illuminate\Support\Facades\DB;

class PurchaseRequestService
{
    public function __construct(
        private PurchaseRequestRepositoryInterface $repository,
        private DocumentSequenceService $documentSequenceService,
        private AuditLogService $auditLogService,
    ) {}

    public function paginate()
    {
        return $this->repository->paginate();
    }

    public function create(
        PurchaseRequestDTO $dto
    )
    {
        return DB::transaction(function () use ($dto) {

            $pr = $this->repository->create([
                'pr_no' => $this
                    ->documentSequenceService
                    ->next('PR'),

                'pr_date' => now(),

                'warehouse_id' => $dto->warehouseId,

                'remarks' => $dto->remarks,

                'status' => 'DRAFT',

                'created_by' => $dto->createdBy,
            ]);

            foreach ($dto->lines as $line) {

                PurchaseRequestDetail::create([
                    'purchase_request_id' => $pr->id,
                    'item_id' => $line->itemId,
                    'qty' => $line->qty,
                    'remarks' => $line->remarks,
                ]);
            }

            $this->auditLogService->log(
                module: 'Purchase Request',
                action: 'CREATE',
                referenceType: 'PurchaseRequest',
                referenceId: $pr->id,
                oldValues: null,
                newValues: [
                    'pr_no' => $pr->pr_no,
                    'status' => $pr->status,
                ]
            );

            return $pr->load(
                'details'
            );
        });
    }
}