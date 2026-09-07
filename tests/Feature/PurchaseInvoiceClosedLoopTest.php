<?php

namespace Tests\Feature;

use App\DTO\PurchaseInvoiceDTO;
use App\DTO\PurchaseInvoiceLineDTO;
use App\Models\Journal;
use App\Services\PurchaseInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;

class PurchaseInvoiceClosedLoopTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    protected function setUp(): void
    {
        parent::setUp();

        $this->data =
            InventoryReconciliationTestData::create();

        /*
        |--------------------------------------------------------------------------
        | Purchase Invoice Document Sequence
        |--------------------------------------------------------------------------
        */

        DB::table('document_sequences')
            ->updateOrInsert(
                [
                    'document_type' =>
                        'INV',
                ],
                [
                    'prefix' =>
                        'INV',

                    'current_number' =>
                        0,

                    'padding' =>
                        5,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]
            );
    }

    public function test_purchase_invoice_uses_company_grni_and_ap_account_mapping(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Liability Group
        |--------------------------------------------------------------------------
        */

        $liabilityGroupId =
            DB::table('account_groups')
                ->where(
                    'code',
                    'LIA-T'
                )
                ->value('id');

        $this->assertNotNull(
            $liabilityGroupId
        );

        /*
        |--------------------------------------------------------------------------
        | Alternative GRNI
        |--------------------------------------------------------------------------
        */

        $alternativeGrniAccountId =
            DB::table('accounts')
                ->insertGetId([
                    'account_group_id' =>
                        $liabilityGroupId,

                    'code' =>
                        '2998-T',

                    'name' =>
                        'Alternative GRNI Test',

                    'normal_balance' =>
                        'CREDIT',

                    'is_header' =>
                        false,

                    'is_active' =>
                        true,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        /*
        |--------------------------------------------------------------------------
        | Alternative AP
        |--------------------------------------------------------------------------
        */

        $alternativeApAccountId =
            DB::table('accounts')
                ->insertGetId([
                    'account_group_id' =>
                        $liabilityGroupId,

                    'code' =>
                        '2997-T',

                    'name' =>
                        'Alternative AP Test',

                    'normal_balance' =>
                        'CREDIT',

                    'is_header' =>
                        false,

                    'is_active' =>
                        true,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        /*
        |--------------------------------------------------------------------------
        | Legacy AP Account
        |--------------------------------------------------------------------------
        |
        | Production code saat ini masih hard-coded ke 2001.
        |
        | Account ini sengaja dibuat agar current code bisa menyelesaikan
        | journal dan RED test gagal tepat pada assertion dynamic mapping,
        | bukan gagal karena account 2001 tidak ditemukan.
        |
        */

        if (
            !DB::table('accounts')
                ->where(
                    'code',
                    '2001'
                )
                ->exists()
        ) {
            DB::table('accounts')
                ->insert([
                    'account_group_id' =>
                        $liabilityGroupId,

                    'code' =>
                        '2001',

                    'name' =>
                        'Legacy Account Payable',

                    'normal_balance' =>
                        'CREDIT',

                    'is_header' =>
                        false,

                    'is_active' =>
                        true,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Change Company Mapping
        |--------------------------------------------------------------------------
        */

        DB::table(
            'accounting_account_mappings'
        )
            ->where(
                'company_id',
                $this->data['company_id']
            )
            ->update([
                'grni_account_id' =>
                    $alternativeGrniAccountId,

                'ap_account_id' =>
                    $alternativeApAccountId,

                'updated_at' =>
                    now(),
            ]);

        /*
        |--------------------------------------------------------------------------
        | Purchase Order
        |--------------------------------------------------------------------------
        */

        $purchaseOrderId =
            DB::table('purchase_orders')
                ->insertGetId([
                    'po_no' =>
                        'PO-PI-TEST-001',

                    'purchase_request_id' =>
                        null,

                    'po_date' =>
                        '2026-08-08',

                    'supplier_name' =>
                        'Supplier PI Test',

                    'remarks' =>
                        'Purchase Invoice test',

                    'status' =>
                        'COMPLETED',

                    'created_by' =>
                        $this->data['user_id'],

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        /*
        |--------------------------------------------------------------------------
        | Goods Receipt
        |--------------------------------------------------------------------------
        */

        $goodsReceiptId =
            DB::table('goods_receipts')
                ->insertGetId([
                    'gr_no' =>
                        'GR-PI-TEST-001',

                    'purchase_order_id' =>
                        $purchaseOrderId,

                    'receipt_date' =>
                        '2026-08-08',

                    'status' =>
                        'POSTED',

                    'remarks' =>
                        'Purchase Invoice source GR',

                    'created_by' =>
                        $this->data['user_id'],

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        /*
        |--------------------------------------------------------------------------
        | Create Purchase Invoice
        |--------------------------------------------------------------------------
        */

        $invoice =
            app(PurchaseInvoiceService::class)
                ->create(
                    new PurchaseInvoiceDTO(
                        goodsReceiptId:
                            $goodsReceiptId,

                        supplierName:
                            'Supplier PI Test',

                        supplierInvoiceNo:
                            'SUP-INV-001',

                        subtotal:
                            80000,

                        taxAmount:
                            0,

                        grandTotal:
                            80000,

                        createdBy:
                            $this->data['user_id'],

                        lines: [
                            new PurchaseInvoiceLineDTO(
                                itemId:
                                    $this->data['item_id'],

                                qty:
                                    10,

                                unitPrice:
                                    8000,

                                amount:
                                    80000,

                                remarks:
                                    'Dynamic control account test',
                            ),
                        ],
                    )
                );

        /*
        |--------------------------------------------------------------------------
        | Journal Header
        |--------------------------------------------------------------------------
        */

        $journal =
            Journal::query()
                ->where(
                    'reference_type',
                    'PURCHASE_INVOICE'
                )
                ->where(
                    'reference_id',
                    $invoice->id
                )
                ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Dynamic GRNI
        |--------------------------------------------------------------------------
        */

        $grniLine =
            DB::table('journal_details')
                ->where(
                    'journal_id',
                    $journal->id
                )
                ->where(
                    'account_id',
                    $alternativeGrniAccountId
                )
                ->first();

        $this->assertNotNull(
            $grniLine,
            'Purchase Invoice must use the company GRNI account mapping.'
        );

        $this->assertEqualsWithDelta(
            80000,
            (float) $grniLine->debit,
            0.01
        );

        $this->assertEqualsWithDelta(
            0,
            (float) $grniLine->credit,
            0.01
        );

        /*
        |--------------------------------------------------------------------------
        | Dynamic AP
        |--------------------------------------------------------------------------
        */

        $apLine =
            DB::table('journal_details')
                ->where(
                    'journal_id',
                    $journal->id
                )
                ->where(
                    'account_id',
                    $alternativeApAccountId
                )
                ->first();

        $this->assertNotNull(
            $apLine,
            'Purchase Invoice must use the company AP account mapping.'
        );

        $this->assertEqualsWithDelta(
            0,
            (float) $apLine->debit,
            0.01
        );

        $this->assertEqualsWithDelta(
            80000,
            (float) $apLine->credit,
            0.01
        );

        /*
        |--------------------------------------------------------------------------
        | Balanced Journal
        |--------------------------------------------------------------------------
        */

        $journal->load('details');

        $this->assertEqualsWithDelta(
            80000,
            (float)
            $journal->details->sum(
                'debit'
            ),
            0.01
        );

        $this->assertEqualsWithDelta(
            80000,
            (float)
            $journal->details->sum(
                'credit'
            ),
            0.01
        );
    }

    public function test_purchase_invoice_rolls_back_when_company_accounting_mapping_is_missing(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Purchase Order
        |--------------------------------------------------------------------------
        */

        $purchaseOrderId =
            DB::table('purchase_orders')
                ->insertGetId([
                    'po_no' =>
                        'PO-PI-ROLLBACK-001',

                    'purchase_request_id' =>
                        null,

                    'po_date' =>
                        '2026-08-08',

                    'supplier_name' =>
                        'Supplier Rollback Test',

                    'remarks' =>
                        'Purchase Invoice rollback test',

                    'status' =>
                        'COMPLETED',

                    'created_by' =>
                        $this->data['user_id'],

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        /*
        |--------------------------------------------------------------------------
        | Goods Receipt
        |--------------------------------------------------------------------------
        */

        $goodsReceiptId =
            DB::table('goods_receipts')
                ->insertGetId([
                    'gr_no' =>
                        'GR-PI-ROLLBACK-001',

                    'purchase_order_id' =>
                        $purchaseOrderId,

                    'receipt_date' =>
                        '2026-08-08',

                    'status' =>
                        'POSTED',

                    'remarks' =>
                        'Purchase Invoice rollback source',

                    'created_by' =>
                        $this->data['user_id'],

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        /*
        |--------------------------------------------------------------------------
        | Remove Company Mapping
        |--------------------------------------------------------------------------
        */

        DB::table(
            'accounting_account_mappings'
        )
            ->where(
                'company_id',
                $this->data['company_id']
            )
            ->delete();

        /*
        |--------------------------------------------------------------------------
        | Capture Before
        |--------------------------------------------------------------------------
        */

        $beforeInvoices =
            DB::table('purchase_invoices')
                ->count();

        $beforeDetails =
            DB::table('purchase_invoice_details')
                ->count();

        $beforePayables =
            DB::table('account_payables')
                ->count();

        $beforeJournals =
            DB::table('journals')
                ->count();

        /*
        |--------------------------------------------------------------------------
        | Act
        |--------------------------------------------------------------------------
        */

        try {

            app(PurchaseInvoiceService::class)
                ->create(
                    new PurchaseInvoiceDTO(
                        goodsReceiptId:
                            $goodsReceiptId,

                        supplierName:
                            'Supplier Rollback Test',

                        supplierInvoiceNo:
                            'SUP-ROLLBACK-001',

                        subtotal:
                            80000,

                        taxAmount:
                            0,

                        grandTotal:
                            80000,

                        createdBy:
                            $this->data['user_id'],

                        lines: [
                            new PurchaseInvoiceLineDTO(
                                itemId:
                                    $this->data['item_id'],

                                qty:
                                    10,

                                unitPrice:
                                    8000,

                                amount:
                                    80000,

                                remarks:
                                    'Rollback mapping test',
                            ),
                        ],
                    )
                );

            $this->fail(
                'Expected missing accounting mapping exception was not thrown.'
            );

        } catch (\Throwable $exception) {

            $this->assertInstanceOf(
                \RuntimeException::class,
                $exception
            );

            $this->assertSame(
                sprintf(
                    'Accounting account mapping is not configured for company %d.',
                    $this->data['company_id']
                ),
                $exception->getMessage()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Full Rollback
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $beforeInvoices,
            DB::table('purchase_invoices')
                ->count()
        );

        $this->assertSame(
            $beforeDetails,
            DB::table('purchase_invoice_details')
                ->count()
        );

        $this->assertSame(
            $beforePayables,
            DB::table('account_payables')
                ->count()
        );

        $this->assertSame(
            $beforeJournals,
            DB::table('journals')
                ->count()
        );
    }
}