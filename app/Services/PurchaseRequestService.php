<?php

namespace App\Services;

use App\DTO\PurchaseRequestDTO;
use App\Models\PurchaseRequestDetail;
use App\Models\Warehouse;
use App\Models\Item;
use App\Repositories\Contracts\PurchaseRequestRepositoryInterface;
use Illuminate\Support\Facades\DB;

class PurchaseRequestService
{
    public function __construct(
        private PurchaseRequestRepositoryInterface $repository,
        private DocumentSequenceService $documentSequenceService,
        private AuditLogService $auditLogService,
        protected CompanyGuardService $companyGuardService,
    ) {}

    public function paginate()
    {
        return $this->repository->paginate();
    }

    public function create(
        PurchaseRequestDTO $dto
    ) {
        return DB::transaction(
            function () use ($dto) {

                /*
                |--------------------------------------------------------------------------
                | Resolve Company Ownership From Warehouse
                |--------------------------------------------------------------------------
                |
                | Purchase Request ownership ditentukan oleh warehouse.
                | created_by bukan ownership authority.
                |
                */

                $warehouse =
                    Warehouse::query()
                        ->whereKey(
                            $dto->warehouseId
                        )
                        ->firstOrFail();

                $companyId =
                    (int) $warehouse->company_id;

                $this->companyGuardService->assertActorBelongsToCompany(
                        $dto->createdBy,
                        $companyId
                    );

                /*
                |--------------------------------------------------------------------------
                | Create Purchase Request Header
                |--------------------------------------------------------------------------
                */

                $pr =
                    $this->repository->create([
                        'company_id' =>
                            $companyId,

                        'pr_no' =>
                            $this
                                ->documentSequenceService
                                ->next(
                                    $companyId,
                                    'PR'
                                ),

                        'pr_date' =>
                            now(),

                        'warehouse_id' =>
                            $dto->warehouseId,

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
                    | Purchase Request ownership berasal dari Warehouse.
                    | Setiap Item pada PR wajib dimiliki company yang sama.
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

                    PurchaseRequestDetail::create([
                        'purchase_request_id' =>
                            $pr->id,

                        'item_id' =>
                            $item->id,

                        'qty' =>
                            $line->qty,

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
                            'Purchase Request',

                        action:
                            'CREATE',

                        referenceType:
                            'PurchaseRequest',

                        referenceId:
                            $pr->id,

                        oldValues:
                            null,

                        newValues: [
                            'pr_no' =>
                                $pr->pr_no,

                            'company_id' =>
                                $pr->company_id,

                            'status' =>
                                $pr->status,
                        ]
                    );

                return $pr->load(
                    'details'
                );
            }
        );
    }
}