<?php

namespace App\Services;

use App\DTO\SalesInvoiceDTO;
use App\Models\AccountReceivable;
use App\Models\DeliveryOrder;
use App\Models\Item;
use App\Models\Customer;
use App\Repositories\Contracts\SalesInvoiceRepositoryInterface;
use Illuminate\Support\Facades\DB;

class SalesInvoiceService
{
    public function __construct(
        private SalesInvoiceRepositoryInterface $repository,
        private DocumentSequenceService $documentSequenceService,
        private AutoJournalService $autoJournalService,
        private AuditLogService $auditService,
        protected CompanyGuardService $companyGuardService,
        private AccountingAccountResolverService $accountResolver,
    ) {
    }

    public function create(
        SalesInvoiceDTO $dto
    ) {
        return DB::transaction(
            function () use ($dto) {

                /*
                |--------------------------------------------------------------------------
                | VALIDASI DELIVERY ORDER
                |--------------------------------------------------------------------------
                |
                | Delivery Order adalah ownership authority untuk Sales Invoice.
                |
                */

                $deliveryOrder =
                    DeliveryOrder::query()
                        ->whereKey(
                            $dto->deliveryOrderId
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $companyId =
                    (int) $deliveryOrder->company_id;

                /*
                |--------------------------------------------------------------------------
                | ACTOR COMPANY GUARD
                |--------------------------------------------------------------------------
                */

                $this
                    ->companyGuardService
                    ->assertActorBelongsToCompany(
                        $dto->createdBy,
                        $companyId
                    );

                /*
                |--------------------------------------------------------------------------
                | CUSTOMER COMPANY GUARD
                |--------------------------------------------------------------------------
                |
                | Customer wajib berasal dari company yang sama dengan Delivery Order /
                | Sales Invoice.
                |
                */

                $customer =
                    Customer::query()
                        ->whereKey(
                            $dto->customerId
                        )
                        ->firstOrFail();

                if (
                    (int) $customer->company_id
                    !==
                    $companyId
                ) {
                    throw new \RuntimeException(
                        'Customer does not belong to transaction company.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | CEK SUDAH PERNAH DI INVOICE ?
                |--------------------------------------------------------------------------
                */

                $existingInvoice =
                    $this
                        ->repository
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

                foreach (
                    $dto->lines
                    as $line
                ) {
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
                    $this
                        ->repository
                        ->create([
                            'company_id' =>
                                $companyId,

                            'invoice_no' =>
                                $this
                                    ->documentSequenceService
                                    ->next('INV'),

                            'customer_id' =>
                                $customer->id,

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

                foreach (
                    $dto->lines
                    as $line
                ) {
                    $invoice
                        ->details()
                        ->create([
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
                |
                | AR mewarisi company dari Sales Invoice / Delivery Order.
                |
                */

                AccountReceivable::create([
                    'company_id' =>
                        $companyId,

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

                foreach (
                    $dto->lines
                    as $line
                ) {

                    $item =
                        Item::query()
                            ->with([
                                'category.salesAccount',
                            ])
                            ->findOrFail(
                                $line->itemId
                            );

                    /*
                    |--------------------------------------------------------------------------
                    | Validate Sales Account Mapping
                    |--------------------------------------------------------------------------
                    */

                    if (
                        ! $item->category
                        ||
                        ! $item
                            ->category
                            ->salesAccount
                    ) {
                        throw new \RuntimeException(
                            sprintf(
                                'Sales account is not configured for item %d.',
                                $line->itemId
                            )
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Company Accounting Guard
                    |--------------------------------------------------------------------------
                    |
                    | Sales account yang dipetakan pada Item Category wajib
                    | berasal dari company yang sama dengan Sales Invoice /
                    | Delivery Order.
                    |
                    */

                    $salesAccount =
                        $this
                            ->accountResolver
                            ->accountForCompany(
                                (int) $item
                                    ->category
                                    ->sales_account_id,

                                $companyId,

                                'Sales account'
                            );

                    /*
                    |--------------------------------------------------------------------------
                    | Validate Line Amount
                    |--------------------------------------------------------------------------
                    */

                    $lineAmount =
                        (
                            $line->qty
                            *
                            $line->unitPrice
                        )
                        -
                        $line->discount;

                    if (
                        $lineAmount
                        <=
                        0
                    ) {
                        throw new \RuntimeException(
                            sprintf(
                                'Sales invoice line amount for item %d must be greater than zero.',
                                $line->itemId
                            )
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Collect Sales Journal Line
                    |--------------------------------------------------------------------------
                    */

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
                */

                $this
                    ->autoJournalService
                    ->salesInvoice(
                        salesAccount:
                            $salesJournalLines,

                        amount:
                            null,

                        referenceId:
                            $invoice->id,

                        userId:
                            $dto->createdBy,

                        companyId:
                            $companyId,
                    );

                /*
                |--------------------------------------------------------------------------
                | UPDATE DELIVERY ORDER
                |--------------------------------------------------------------------------
                */

                $deliveryOrder->update([
                    'status' =>
                        'INVOICED',
                ]);

                /*
                |--------------------------------------------------------------------------
                | AUDIT LOG
                |--------------------------------------------------------------------------
                */

                $this
                    ->auditService
                    ->log(
                        module:
                            'Sales Invoice',

                        action:
                            'CREATE',

                        referenceType:
                            'SalesInvoice',

                        referenceId:
                            $invoice->id,

                        oldValues:
                            null,

                        newValues: [
                            'invoice_no' =>
                                $invoice->invoice_no,

                            'company_id' =>
                                $companyId,

                            'delivery_order_id' =>
                                $deliveryOrder->id,
                        ]
                    );

                return $invoice->load([
                    'details',
                ]);
            }
        );
    }
}