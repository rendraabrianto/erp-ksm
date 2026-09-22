<?php

namespace App\Services;

use App\DTO\PurchaseInvoiceDTO;
use App\DTO\AccountPayableDTO;

use App\Models\GoodsReceipt;

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
        private CompanyGuardService $companyGuardService,
    ) {}

    public function create(
        PurchaseInvoiceDTO $dto
    )
    {
        return DB::transaction(
            function () use ($dto) {

                /*
                |--------------------------------------------------------------------------
                | Goods Receipt Ownership Authority
                |--------------------------------------------------------------------------
                |
                | Purchase Invoice harus mewarisi company dari Goods Receipt.
                |
                | created_by hanya actor/audit information dan tidak boleh menentukan
                | ownership dokumen.
                |
                */

                $goodsReceipt =
                    GoodsReceipt::query()
                        ->whereKey(
                            $dto->goodsReceiptId
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $companyId =
                    (int) $goodsReceipt->company_id;

                /*
                |--------------------------------------------------------------------------
                | Actor Company Guard
                |--------------------------------------------------------------------------
                |
                | Goods Receipt is the authoritative company ownership source.
                | createdBy must belong to the same company.
                |
                */

                $this->companyGuardService
                    ->assertActorBelongsToCompany(
                        $dto->createdBy,
                        $companyId
                    );

                /*
                |--------------------------------------------------------------------------
                | Create Purchase Invoice
                |--------------------------------------------------------------------------
                */

                $invoice =
                    $this->repository->create([
                        'company_id' =>
                            $companyId,

                        'invoice_no' =>
                            $this
                                ->documentSequenceService
                                ->next(
                                    $companyId,
                                    'INV'
                                ),

                        'invoice_date' =>
                            now(),

                        'goods_receipt_id' =>
                            $goodsReceipt->id,

                        'supplier_name' =>
                            $dto->supplierName,

                        'supplier_invoice_no' =>
                            $dto->supplierInvoiceNo,

                        'subtotal' =>
                            $dto->subtotal,

                        'tax_amount' =>
                            $dto->taxAmount,

                        'grand_total' =>
                            $dto->grandTotal,

                        'status' =>
                            'OPEN',

                        'created_by' =>
                            $dto->createdBy,
                    ]);

                /*
                |--------------------------------------------------------------------------
                | Purchase Invoice Details
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

                            'amount' =>
                                $line->amount,

                            'remarks' =>
                                $line->remarks,
                        ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Create Account Payable
                |--------------------------------------------------------------------------
                |
                | AP mewarisi company Purchase Invoice.
                |
                */

                $this
                    ->accountPayableService
                    ->create(
                        new AccountPayableDTO(
                            companyId:
                                $companyId,

                            referenceType:
                                'PURCHASE_INVOICE',

                            referenceId:
                                $invoice->id,

                            supplierName:
                                $dto->supplierName,

                            invoiceDate:
                                now()->toDateString(),

                            dueDate:
                                now()
                                    ->addDays(30)
                                    ->toDateString(),

                            amount:
                                $dto->grandTotal,
                        )
                    );

                /*
                |--------------------------------------------------------------------------
                | Journal
                |--------------------------------------------------------------------------
                |
                | Accounting mapping harus menggunakan company source document,
                | bukan company creator.
                |
                */

                $this
                    ->autoJournalService
                    ->purchaseInvoice(
                        amount:
                            $dto->grandTotal,

                        referenceId:
                            $invoice->id,

                        userId:
                            $dto->createdBy,

                        companyId:
                            $companyId,
                    );

                /*
                |--------------------------------------------------------------------------
                | Audit
                |--------------------------------------------------------------------------
                */

                $this
                    ->auditService
                    ->log(
                        module:
                            'Purchase Invoice',

                        action:
                            'CREATE',

                        referenceType:
                            'PurchaseInvoice',

                        referenceId:
                            $invoice->id,

                        oldValues:
                            null,

                        newValues: [
                            'invoice_no' =>
                                $invoice->invoice_no,

                            'company_id' =>
                                $invoice->company_id,

                            'goods_receipt_id' =>
                                $goodsReceipt->id,
                        ],
                    );

                return $invoice->load(
                    'details'
                );
            }
        );
    }
}