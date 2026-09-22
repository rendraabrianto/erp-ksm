<?php

namespace Tests\Feature;

use App\DTO\GoodsReceiptDTO;
use App\DTO\GoodsReceiptLineDTO;
use App\DTO\PaymentVoucherDTO;
use App\DTO\PurchaseInvoiceDTO;
use App\Models\AccountPayable;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\GoodsReceipt;
use App\Models\Journal;
use App\Models\PaymentVoucher;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use App\Models\StockLedger;
use App\Models\User;
use App\Services\GoodsReceiptService;
use App\Services\PaymentVoucherService;
use App\Services\PurchaseInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;

class PurchaseDownstreamCompanyGuardTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    private User $foreignUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->data =
            InventoryReconciliationTestData::create();

        /*
        |--------------------------------------------------------------------------
        | Foreign Company + Actor
        |--------------------------------------------------------------------------
        */

        $foreignCompany =
            Company::create([
                'code' =>
                    'PUR-B',

                'name' =>
                    'Purchase Guard Company B',

                'is_active' =>
                    true,
            ]);

        $this->foreignUser =
            User::create([
                'company_id' =>
                    $foreignCompany->id,

                'branch_id' =>
                    null,

                'name' =>
                    'Foreign Purchase Actor',

                'email' =>
                    'foreign-purchase@example.test',

                'password' =>
                    'password',

                'is_active' =>
                    true,
            ]);

        /*
        |--------------------------------------------------------------------------
        | Document Sequences
        |--------------------------------------------------------------------------
        */

        $this->createSequence(
            $this->data['company_id'],
            'GR',
            'GR'
        );

        $this->createSequence(
            $this->data['company_id'],
            'INV',
            'INV'
        );

        $this->createSequence(
            $this->data['company_id'],
            'PV',
            'PV'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Goods Receipt Guard
    |--------------------------------------------------------------------------
    */

    public function test_goods_receipt_rejects_creator_from_different_company():
        void
    {
        [$po, $poDetail] =
            $this->createPurchaseOrder();

        $grCountBefore =
            GoodsReceipt::count();

        $ledgerCountBefore =
            StockLedger::count();

        $journalCountBefore =
            Journal::count();

        $receivedQtyBefore =
            (float) $poDetail->received_qty;

        try {

            app(
                GoodsReceiptService::class
            )->create(
                new GoodsReceiptDTO(
                    purchaseOrderId:
                        $po->id,

                    supplierName:
                        'Supplier Guard Test',

                    remarks:
                        'Cross-company GR test',

                    warehouseId:
                        $this->data['warehouse_id'],

                    createdBy:
                        $this->foreignUser->id,

                    lines: [
                        new GoodsReceiptLineDTO(
                            itemId:
                                $this->data['item_id'],

                            qty:
                                10,

                            unitPrice:
                                8000,

                            remarks:
                                'Cross-company GR line',

                            purchaseOrderDetailId:
                                $poDetail->id,
                        ),
                    ],

                    receiptDate:
                        '2026-08-08',
                )
            );

            $this->fail(
                'Expected cross-company actor rejection.'
            );

        } catch (RuntimeException $exception) {

            $this->assertSame(
                'Actor does not belong to transaction company.',
                $exception->getMessage()
            );
        }

        $poDetail->refresh();

        $this->assertSame(
            $grCountBefore,
            GoodsReceipt::count()
        );

        $this->assertSame(
            $ledgerCountBefore,
            StockLedger::count()
        );

        $this->assertSame(
            $journalCountBefore,
            Journal::count()
        );

        $this->assertEqualsWithDelta(
            $receivedQtyBefore,
            (float) $poDetail->received_qty,
            0.0001
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Purchase Invoice Guard
    |--------------------------------------------------------------------------
    */

    public function test_purchase_invoice_rejects_creator_from_different_company():
        void
    {
        $goodsReceipt =
            $this->createGoodsReceiptHeader();

        $invoiceCountBefore =
            PurchaseInvoice::count();

        $apCountBefore =
            AccountPayable::count();

        $journalCountBefore =
            Journal::count();

        try {

            app(
                PurchaseInvoiceService::class
            )->create(
                new PurchaseInvoiceDTO(
                    goodsReceiptId:
                        $goodsReceipt->id,

                    supplierName:
                        'Supplier Guard Test',

                    supplierInvoiceNo:
                        'SUP-GUARD-001',

                    subtotal:
                        80000,

                    taxAmount:
                        0,

                    grandTotal:
                        80000,

                    createdBy:
                        $this->foreignUser->id,

                    lines:
                        [],
                )
            );

            $this->fail(
                'Expected cross-company actor rejection.'
            );

        } catch (RuntimeException $exception) {

            $this->assertSame(
                'Actor does not belong to transaction company.',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            $invoiceCountBefore,
            PurchaseInvoice::count()
        );

        $this->assertSame(
            $apCountBefore,
            AccountPayable::count()
        );

        $this->assertSame(
            $journalCountBefore,
            Journal::count()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Payment Voucher Guard
    |--------------------------------------------------------------------------
    */

    public function test_payment_voucher_rejects_creator_from_different_company():
        void
    {
        $ap =
            $this->createAccountPayable();

        $voucherCountBefore =
            PaymentVoucher::count();

        $journalCountBefore =
            Journal::count();

        $paidBefore =
            (float) $ap->paid_amount;

        $balanceBefore =
            (float) $ap->balance_amount;

        $statusBefore =
            (string) $ap->status;

        try {

            app(
                PaymentVoucherService::class
            )->create(
                new PaymentVoucherDTO(
                    accountPayableId:
                        $ap->id,

                    cashBankAccountId:
                        $this->data[
                            'inventory_account_id'
                        ],

                    amount:
                        10000,

                    paymentMethod:
                        'BANK_TRANSFER',

                    remarks:
                        'Cross-company PV test',

                    createdBy:
                        $this->foreignUser->id,
                )
            );

            $this->fail(
                'Expected cross-company actor rejection.'
            );

        } catch (RuntimeException $exception) {

            $this->assertSame(
                'Actor does not belong to transaction company.',
                $exception->getMessage()
            );
        }

        $ap->refresh();

        $this->assertSame(
            $voucherCountBefore,
            PaymentVoucher::count()
        );

        $this->assertSame(
            $journalCountBefore,
            Journal::count()
        );

        $this->assertEqualsWithDelta(
            $paidBefore,
            (float) $ap->paid_amount,
            0.0001
        );

        $this->assertEqualsWithDelta(
            $balanceBefore,
            (float) $ap->balance_amount,
            0.0001
        );

        $this->assertSame(
            $statusBefore,
            (string) $ap->status
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helper - Purchase Order
    |--------------------------------------------------------------------------
    */

    private function createPurchaseOrder():
        array
    {
        $po =
            PurchaseOrder::create([
                'company_id' =>
                    $this->data['company_id'],

                'po_no' =>
                    'PO-GUARD-'
                    .
                    uniqid(),

                'purchase_request_id' =>
                    null,

                'po_date' =>
                    '2026-08-08',

                'supplier_name' =>
                    'Supplier Guard Test',

                'remarks' =>
                    'Purchase guard test',

                'status' =>
                    'APPROVED',

                'created_by' =>
                    $this->data['user_id'],
            ]);

        $detail =
            PurchaseOrderDetail::create([
                'purchase_order_id' =>
                    $po->id,

                'item_id' =>
                    $this->data['item_id'],

                'qty' =>
                    20,

                'received_qty' =>
                    0,

                'unit_price' =>
                    8000,

                'remarks' =>
                    'Purchase guard detail',
            ]);

        return [
            $po,
            $detail,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Helper - Goods Receipt Header
    |--------------------------------------------------------------------------
    */

    private function createGoodsReceiptHeader():
        GoodsReceipt
    {
        [$po] =
            $this->createPurchaseOrder();

        return GoodsReceipt::create([
            'company_id' =>
                $this->data['company_id'],

            'gr_no' =>
                'GR-GUARD-'
                .
                uniqid(),

            'purchase_order_id' =>
                $po->id,

            'receipt_date' =>
                '2026-08-08',

            'status' =>
                'POSTED',

            'remarks' =>
                'Purchase invoice guard parent',

            'created_by' =>
                $this->data['user_id'],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper - Account Payable
    |--------------------------------------------------------------------------
    */

    private function createAccountPayable():
        AccountPayable
    {
        return AccountPayable::create([
            'company_id' =>
                $this->data['company_id'],

            'reference_type' =>
                'PURCHASE_INVOICE',

            'reference_id' =>
                999001,

            'supplier_name' =>
                'Supplier Guard Test',

            'invoice_date' =>
                '2026-08-08',

            'due_date' =>
                '2026-09-08',

            'amount' =>
                80000,

            'paid_amount' =>
                0,

            'balance_amount' =>
                80000,

            'status' =>
                'UNPAID',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper - Document Sequence
    |--------------------------------------------------------------------------
    */

    private function createSequence(
        int $companyId,
        string $documentType,
        string $prefix
    ): void {

        DocumentSequence::firstOrCreate(
            [
                'company_id' =>
                    $companyId,
                'document_type' =>
                    $documentType,
            ],
            [
                'prefix' =>
                    $prefix,

                'description' =>
                    $documentType
                    .
                    ' Company Guard Test',

                'current_number' =>
                    0,

                'padding' =>
                    5,

                'is_active' =>
                    true,
            ]
        );
    }
}