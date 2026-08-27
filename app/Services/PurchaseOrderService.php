<?php

namespace App\Services;

use App\DTO\PurchaseOrderDTO;
use App\Models\PurchaseOrderDetail;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function __construct(
        private PurchaseOrderRepositoryInterface $repository,
        private DocumentSequenceService $documentSequenceService,
        private AuditLogService $auditLogService,
    ) {}

    public function create(
        PurchaseOrderDTO $dto
    )
    {
        return DB::transaction(function () use ($dto) {

            $po = $this->repository->create([
                'po_no' => $this
                    ->documentSequenceService
                    ->next('PO'),

                'purchase_request_id'
                    => $dto->purchaseRequestId,

                'po_date' => now(),

                'supplier_name'
                    => $dto->supplierName,

                'remarks'
                    => $dto->remarks,

                'status' => 'DRAFT',

                'created_by'
                    => $dto->createdBy,
            ]);

            foreach ($dto->lines as $line) {

                PurchaseOrderDetail::create([
                    'purchase_order_id'
                        => $po->id,

                    'item_id'
                        => $line->itemId,

                    'qty'
                        => $line->qty,

                    'unit_price'
                        => $line->unitPrice,

                    'received_qty'
                        => 0,

                    'remarks'
                        => $line->remarks,
                ]);
            }

            $this->auditLogService->log(
                module: 'Purchase Order',
                action: 'CREATE',
                referenceType: 'PurchaseOrder',
                referenceId: $po->id,
                oldValues: null,
                newValues: [
                    'po_no' => $po->po_no,
                ]
            );

            return $po->load('details');
        });
    }
}