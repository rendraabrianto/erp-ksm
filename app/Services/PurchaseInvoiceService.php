<?php

namespace App\Services;

use App\DTO\PurchaseInvoiceDTO;
use App\DTO\AccountPayableDTO;

use App\Repositories\Contracts\PurchaseInvoiceRepositoryInterface;

use Illuminate\Support\Facades\DB;

class PurchaseInvoiceService
{
    public function __construct(
        private PurchaseInvoiceRepositoryInterface $repository,
        private DocumentSequenceService $documentSequenceService,
        private AccountPayableService $accountPayableService,
        private AutoJournalService $autoJournalService,
        private AuditLogService $auditService,
    ) {}

    public function create(
        PurchaseInvoiceDTO $dto
    )
    {
        return DB::transaction(
            function () use ($dto) {

                $invoice =
                    $this->repository->create([
                        'invoice_no' =>
                            $this->documentSequenceService
                                ->next('INV'),

                        'invoice_date' => now(),

                        'goods_receipt_id'
                            => $dto->goodsReceiptId,

                        'supplier_name'
                            => $dto->supplierName,

                        'supplier_invoice_no'
                            => $dto->supplierInvoiceNo,

                        'subtotal'
                            => $dto->subtotal,

                        'tax_amount'
                            => $dto->taxAmount,

                        'grand_total'
                            => $dto->grandTotal,

                        'status'
                            => 'OPEN',

                        'created_by'
                            => $dto->createdBy,
                    ]);

                foreach ($dto->lines as $line) {

                    $invoice->details()->create([
                        'item_id'
                            => $line->itemId,

                        'qty'
                            => $line->qty,

                        'unit_price'
                            => $line->unitPrice,

                        'amount'
                            => $line->amount,

                        'remarks'
                            => $line->remarks,
                    ]);
                }

                /*
                |--------------------------------
                | Create AP
                |--------------------------------
                */

                $this->accountPayableService
                    ->create(
                        new AccountPayableDTO(
                            referenceType : 'PURCHASE_INVOICE',
                            referenceId   : $invoice->id,
                            supplierName  : $dto->supplierName,
                            invoiceDate   : now()->toDateString(),
                            dueDate       : now()
                                ->addDays(30)
                                ->toDateString(),
                            amount        : $dto->grandTotal
                        )
                    );

                /*
                |--------------------------------
                | Journal
                |--------------------------------
                */

                $this->autoJournalService
                    ->purchaseInvoice(
                        amount      : $dto->grandTotal,
                        referenceId : $invoice->id,
                        userId      : $dto->createdBy
                    );

                /*
                |--------------------------------
                | Audit
                |--------------------------------
                */

                $this->auditService->log(
                    module : 'Purchase Invoice',
                    action : 'CREATE',
                    referenceType : 'PurchaseInvoice',
                    referenceId : $invoice->id,
                    oldValues : null,
                    newValues : [
                        'invoice_no'
                            => $invoice->invoice_no,
                    ]
                );

                return $invoice->load(
                    'details'
                );
            }
        );
    }
}