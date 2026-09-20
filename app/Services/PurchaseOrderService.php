<?php

namespace App\Services;

use App\DTO\PurchaseOrderDTO;
use App\Models\Item;
use App\Models\PurchaseOrderDetail;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function __construct(
        private PurchaseOrderRepositoryInterface $repository,
        private DocumentSequenceService $documentSequenceService,
        private AuditLogService $auditLogService,
        protected CompanyGuardService $companyGuardService,
    ) {}

    public function create(
        PurchaseOrderDTO $dto
    ) {
        return DB::transaction(
            function () use ($dto) {

                /*
                |--------------------------------------------------------------------------
                | Resolve Company Ownership
                |--------------------------------------------------------------------------
                |
                | Rule:
                |
                | 1. PO dari Purchase Request:
                |    company_id = purchase_request.company_id
                |
                | 2. PO manual tanpa Purchase Request:
                |    company_id = creator.company_id
                |
                | Actor lintas company belum ditolak di fase ini.
                | Cross-company authorization guard akan dilakukan di G6.
                |
                */

                if ($dto->purchaseRequestId !== null) {
                    $purchaseRequest = PurchaseRequest::query()
                        ->whereKey($dto->purchaseRequestId)
                        ->firstOrFail();

                    $companyId = (int) $purchaseRequest->company_id;
                } else {
                    $creator = User::query()
                        ->whereKey($dto->createdBy)
                        ->firstOrFail();

                    $companyId = (int) $creator->company_id;
                }

                $this->companyGuardService->assertActorBelongsToCompany(
                    $dto->createdBy,
                    $companyId
                );

                /*
                |--------------------------------------------------------------------------
                | Create Purchase Order Header
                |--------------------------------------------------------------------------
                */

                $po =
                    $this->repository->create([
                        'company_id' =>
                            $companyId,

                        'po_no' =>
                            $this
                                ->documentSequenceService
                                ->next('PO'),

                        'purchase_request_id' =>
                            $dto->purchaseRequestId,

                        'po_date' =>
                            now(),

                        'supplier_name' =>
                            $dto->supplierName,

                        'remarks' =>
                            $dto->remarks,

                        'status' =>
                            'DRAFT',

                        'created_by' =>
                            $dto->createdBy,
                    ]);

                /*
                |--------------------------------------------------------------------------
                | Create Details
                |--------------------------------------------------------------------------
                */

                foreach (
                    $dto->lines
                    as $line
                ) {

                    /*
                    |--------------------------------------------------------------------------
                    | Item Company Ownership
                    |--------------------------------------------------------------------------
                    |
                    | Purchase Order wajib hanya berisi Item milik company PO.
                    |
                    | Untuk PO dari Purchase Request, company berasal dari parent PR.
                    | Untuk PO manual, company berasal dari creator.
                    |
                    */

                    $item =
                        Item::query()
                            ->whereKey(
                                $line->itemId
                            )
                            ->firstOrFail();

                    if (
                        (int) $item->company_id
                        !==
                        $companyId
                    ) {
                        throw new \RuntimeException(
                            'Item does not belong to transaction company.'
                        );
                    }

                    PurchaseOrderDetail::create([
                        'purchase_order_id' =>
                            $po->id,

                        'item_id' =>
                            $item->id,

                        'qty' =>
                            $line->qty,

                        'unit_price' =>
                            $line->unitPrice,

                        'received_qty' =>
                            0,

                        'remarks' =>
                            $line->remarks,
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Audit Log
                |--------------------------------------------------------------------------
                */

                $this
                    ->auditLogService
                    ->log(
                        module:
                            'Purchase Order',

                        action:
                            'CREATE',

                        referenceType:
                            'PurchaseOrder',

                        referenceId:
                            $po->id,

                        oldValues:
                            null,

                        newValues: [
                            'po_no' =>
                                $po->po_no,

                            'company_id' =>
                                $po->company_id,

                            'purchase_request_id' =>
                                $po->purchase_request_id,
                        ]
                    );

                return $po->load(
                    'details'
                );
            }
        );
    }
}