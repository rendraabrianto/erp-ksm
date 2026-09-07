<?php

namespace App\Services;

use App\DTO\SalesInvoiceDTO;
use App\Models\AccountReceivable;
use App\Models\DeliveryOrder;
use App\Models\Item;
use App\Repositories\Contracts\SalesInvoiceRepositoryInterface;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class SalesInvoiceService
{
    public function __construct(

        private SalesInvoiceRepositoryInterface $repository,
        private DocumentSequenceService $documentSequenceService,
        private AutoJournalService $autoJournalService,
        private AuditLogService $auditService,

    ) {}

    public function create(
        SalesInvoiceDTO $dto
    )
    {
        return DB::transaction(function () use ($dto) {

            /*
            |--------------------------------------------------------------------------
            | Resolve Company
            |--------------------------------------------------------------------------
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
            | VALIDASI DELIVERY ORDER
            |--------------------------------------------------------------------------
            */

            $deliveryOrder =
                DeliveryOrder::findOrFail(
                    $dto->deliveryOrderId
                );

            /*
            |--------------------------------------------------------------------------
            | CEK SUDAH PERNAH DI INVOICE ?
            |--------------------------------------------------------------------------
            */

            $existingInvoice =
                $this->repository
                    ->findByDeliveryOrder(
                        $dto->deliveryOrderId
                    );

            if ($existingInvoice) {

                throw new \Exception(
                    'Delivery Order sudah dibuat Invoice.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | HITUNG SUBTOTAL
            |--------------------------------------------------------------------------
            */

            $subtotal = 0;

            foreach ($dto->lines as $line) {

                $subtotal +=
                    (
                        $line->qty
                        *
                        $line->unitPrice
                    )
                    -
                    $line->discount;
            }

            /*
            |--------------------------------------------------------------------------
            | CREATE SALES INVOICE HEADER
            |--------------------------------------------------------------------------
            */

            $invoice =
                $this->repository->create([

                    'invoice_no' =>
                        $this->documentSequenceService
                            ->next('INV'),

                    'customer_id' =>
                        $dto->customerId,

                    'delivery_order_id' =>
                        $dto->deliveryOrderId,

                    'invoice_date' =>
                        now()->toDateString(),

                    'due_date' =>
                        $dto->dueDate,

                    'subtotal' =>
                        $subtotal,

                    'discount_amount' =>
                        0,

                    'tax_amount' =>
                        0,

                    'grand_total' =>
                        $subtotal,

                    'status' =>
                        'POSTED',

                    'remarks' =>
                        $dto->remarks,

                    'created_by' =>
                        $dto->createdBy,
                ]);

            /*
            |--------------------------------------------------------------------------
            | CREATE DETAIL
            |--------------------------------------------------------------------------
            */

            foreach ($dto->lines as $line) {

                $invoice->details()->create([

                    'item_id' =>
                        $line->itemId,

                    'qty' =>
                        $line->qty,

                    'unit_price' =>
                        $line->unitPrice,

                    'discount' =>
                        $line->discount,

                    'line_total' =>
                        (
                            $line->qty
                            *
                            $line->unitPrice
                        )
                        -
                        $line->discount,

                    'remarks' =>
                        $line->remarks,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | CREATE ACCOUNT RECEIVABLE
            |--------------------------------------------------------------------------
            */

            AccountReceivable::create([

                'customer_id' =>
                    $invoice->customer_id,

                'sales_invoice_id' =>
                    $invoice->id,

                'invoice_date' =>
                    $invoice->invoice_date,

                'due_date' =>
                    $invoice->due_date,

                'amount' =>
                    $invoice->grand_total,

                'paid_amount' =>
                    0,

                'balance_amount' =>
                    $invoice->grand_total,

                'status' =>
                    'OPEN',

                'remarks' =>
                    $invoice->remarks,
            ]);

            /*
            |--------------------------------------------------------------------------
            | BUILD SALES JOURNAL LINES
            |--------------------------------------------------------------------------
            */

            $salesJournalLines = [];

            foreach ($dto->lines as $line) {

                $item =
                    Item::with([
                        'category.salesAccount',
                    ])
                        ->findOrFail(
                            $line->itemId
                        );

                $salesAccount =
                    $item
                        ->category
                        ?->salesAccount;

                if (!$salesAccount) {
                    throw new \RuntimeException(
                        sprintf(
                            'Sales account is not configured for item %d.',
                            $line->itemId
                        )
                    );
                }

                if ($salesAccount->is_header) {
                    throw new \RuntimeException(
                        sprintf(
                            'Sales account for item %d cannot be a header account.',
                            $line->itemId
                        )
                    );
                }

                if (!$salesAccount->is_active) {
                    throw new \RuntimeException(
                        sprintf(
                            'Sales account for item %d is inactive.',
                            $line->itemId
                        )
                    );
                }

                $lineAmount =
                    (
                        $line->qty
                        *
                        $line->unitPrice
                    )
                    -
                    $line->discount;

                if ($lineAmount <= 0) {
                    throw new \RuntimeException(
                        sprintf(
                            'Sales invoice line amount for item %d must be greater than zero.',
                            $line->itemId
                        )
                    );
                }

                $salesJournalLines[] = [
                    'salesAccount' =>
                        $salesAccount->code,

                    'amount' =>
                        (float) $lineAmount,
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | AUTO JOURNAL
            |--------------------------------------------------------------------------
            |
            | Dr Piutang Dagang
            | Cr Penjualan
            |
            |--------------------------------------------------------------------------
            */

            $this->autoJournalService
            ->salesInvoice(
                salesAccount:$salesJournalLines,
                amount:null,
                referenceId:$invoice->id,
                userId:$dto->createdBy,
                companyId:$companyId,
            );

            /*
            |--------------------------------------------------------------------------
            | UPDATE DELIVERY ORDER
            |--------------------------------------------------------------------------
            */

            $deliveryOrder->update([

                'status' =>
                    'INVOICED'
            ]);

            /*
            |--------------------------------------------------------------------------
            | AUDIT LOG
            |--------------------------------------------------------------------------
            */

            $this->auditService->log(

                module :
                    'Sales Invoice',

                action :
                    'CREATE',

                referenceType :
                    'SalesInvoice',

                referenceId :
                    $invoice->id,

                oldValues :
                    null,

                newValues : [

                    'invoice_no' =>
                        $invoice->invoice_no,
                ]
            );

            return $invoice->load([
                'details'
            ]);
        });
    }
}