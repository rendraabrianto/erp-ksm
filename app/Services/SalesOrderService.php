<?php

namespace App\Services;

use App\DTO\SalesOrderDTO;
use App\Models\User;
use App\Models\Item;
use App\Repositories\Contracts\SalesOrderRepositoryInterface;
use Illuminate\Support\Facades\DB;

class SalesOrderService
{
    public function __construct(
        private SalesOrderRepositoryInterface $repository,
        private DocumentSequenceService $documentSequenceService,
        private AuditLogService $auditService,
    ) {}

    public function create(
        SalesOrderDTO $dto
    ) {
        return DB::transaction(
            function () use ($dto) {

                /*
                |--------------------------------------------------------------------------
                | Resolve Company Ownership
                |--------------------------------------------------------------------------
                |
                | Sales Order adalah root document pada sales cycle saat ini.
                | Karena belum ada parent business document, ownership diambil
                | dari company milik creator.
                |
                | G6 nanti akan menangani cross-company actor authorization.
                |
                */

                $user =
                    User::query()
                        ->whereKey(
                            $dto->createdBy
                        )
                        ->firstOrFail();

                $companyId =
                    (int) $user->company_id;

                /*
                |--------------------------------------------------------------------------
                | Create Sales Order
                |--------------------------------------------------------------------------
                */

                $so =
                    $this
                        ->repository
                        ->create([
                            'company_id' =>
                                $companyId,

                            'so_no' =>
                                $this
                                    ->documentSequenceService
                                    ->next('SO'),

                            'customer_id' =>
                                $dto->customerId,

                            'order_date' =>
                                now()->toDateString(),

                            'delivery_date' =>
                                $dto->deliveryDate,

                            'status' =>
                                'APPROVED',

                            'remarks' =>
                                $dto->remarks,

                            'created_by' =>
                                $dto->createdBy,
                        ]);

                /*
                |--------------------------------------------------------------------------
                | Details
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
                    | Sales Order adalah root document pada sales cycle.
                    | Setiap Item pada Sales Order wajib dimiliki company yang sama
                    | dengan company Sales Order.
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

                    $so
                        ->details()
                        ->create([
                            'item_id' =>
                                $item->id,

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

                /*
                |--------------------------------------------------------------------------
                | Audit
                |--------------------------------------------------------------------------
                */

                $this
                    ->auditService
                    ->log(
                        module:
                            'Sales Order',

                        action:
                            'CREATE',

                        referenceType:
                            'SalesOrder',

                        referenceId:
                            $so->id,

                        oldValues:
                            null,

                        newValues: [
                            'so_no' =>
                                $so->so_no,

                            'company_id' =>
                                $companyId,
                        ]
                    );

                return $so->load(
                    'details'
                );
            }
        );
    }
}