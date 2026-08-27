<?php

namespace App\Services;

use App\DTO\SalesOrderDTO;
use Illuminate\Support\Facades\DB;
use App\Repositories\Contracts\SalesOrderRepositoryInterface;

class SalesOrderService
{
    public function __construct(

        private SalesOrderRepositoryInterface $repository,

        private DocumentSequenceService $documentSequenceService,

        private AuditLogService $auditService,
    ) {}

    public function create(
        SalesOrderDTO $dto
    )
    {
        return DB::transaction(
            function () use ($dto) {

                $so =
                    $this->repository->create([

                        'so_no' =>
                            $this
                                ->documentSequenceService
                                ->next('SO'),

                        'customer_id' =>
                            $dto->customerId,

                        'order_date' =>
                            now()
                                ->toDateString(),

                        'delivery_date' =>
                            $dto->deliveryDate,

                        'status' =>
                            'APPROVED',

                        'remarks' =>
                            $dto->remarks,

                        'created_by' =>
                            $dto->createdBy,
                    ]);

                foreach (
                    $dto->lines
                    as $line
                ) {

                    $so->details()->create([

                        'item_id' =>
                            $line->itemId,

                        'qty' =>
                            $line->qty,

                        'unit_price' =>
                            $line->unitPrice,

                        'discount' =>
                            $line->discount,

                        'remarks' =>
                            $line->remarks,
                    ]);
                }

                $this->auditService->log(

                    module : 'Sales Order',

                    action : 'CREATE',

                    referenceType : 'SalesOrder',

                    referenceId : $so->id,

                    oldValues : null,

                    newValues : [
                        'so_no' => $so->so_no,
                    ]
                );

                return $so->load(
                    'details'
                );
            }
        );
    }
}