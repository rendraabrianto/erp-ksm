<?php

namespace Tests\Feature;

use App\DTO\SalesInvoiceDTO;
use App\DTO\SalesInvoiceLineDTO;
use App\Models\Journal;
use App\Services\SalesInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;

class SalesInvoiceClosedLoopTest extends TestCase
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
        | Sales Invoice Document Sequence
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

    public function test_sales_invoice_uses_company_ar_account_mapping(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Account Groups
        |--------------------------------------------------------------------------
        */

        $assetGroupId =
            DB::table('account_groups')
                ->where(
                    'code',
                    'AST-T'
                )
                ->value('id');

        $this->assertNotNull(
            $assetGroupId
        );

        $revenueGroupId =
            DB::table('account_groups')
                ->insertGetId([
                    'code' =>
                        'REV-SI',

                    'name' =>
                        'Revenue Sales Invoice Test',

                    'is_active' =>
                        true,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        /*
        |--------------------------------------------------------------------------
        | Alternative AR
        |--------------------------------------------------------------------------
        */

        $alternativeArAccountId =
            DB::table('accounts')
                ->insertGetId([
                    'account_group_id' =>
                        $assetGroupId,

                    'code' =>
                        '1198-SI',

                    'name' =>
                        'Alternative AR Sales Invoice',

                    'normal_balance' =>
                        'DEBIT',

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
        | Legacy AR
        |--------------------------------------------------------------------------
        |
        | Current AutoJournalService masih menggunakan code 1101.
        | Account ini dibuat supaya RED berhenti tepat pada assertion mapping.
        |
        */

        if (
            !DB::table('accounts')
                ->where(
                    'code',
                    '1101'
                )
                ->exists()
        ) {
            DB::table('accounts')
                ->insert([
                    'account_group_id' =>
                        $assetGroupId,

                    'code' =>
                        '1101',

                    'name' =>
                        'Legacy Accounts Receivable',

                    'normal_balance' =>
                        'DEBIT',

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
        | Sales Revenue
        |--------------------------------------------------------------------------
        */

        $salesAccountId =
            DB::table('accounts')
                ->insertGetId([
                    'account_group_id' =>
                        $revenueGroupId,

                    'code' =>
                        '4101-SI',

                    'name' =>
                        'Sales Revenue Test',

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
        | Configure Item Sales Account
        |--------------------------------------------------------------------------
        */

        $item =
            DB::table('items')
                ->where(
                    'id',
                    $this->data['item_id']
                )
                ->first();

        $this->assertNotNull(
            $item
        );

        DB::table('item_categories')
            ->where(
                'id',
                $item->item_category_id
            )
            ->update([
                'sales_account_id' =>
                    $salesAccountId,

                'updated_at' =>
                    now(),
            ]);

        /*
        |--------------------------------------------------------------------------
        | Change Company AR Mapping
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
                'ar_account_id' =>
                    $alternativeArAccountId,

                'updated_at' =>
                    now(),
            ]);

        /*
        |--------------------------------------------------------------------------
        | Customer
        |--------------------------------------------------------------------------
        */

        $customerId =
            DB::table('customers')
                ->insertGetId([
                    'code' =>
                        'CUS-SI-001',

                    'name' =>
                        'Customer Sales Invoice Test',

                    'credit_limit' =>
                        1000000,

                    'credit_days' =>
                        30,

                    'is_active' =>
                        true,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        /*
        |--------------------------------------------------------------------------
        | Sales Order
        |--------------------------------------------------------------------------
        */

        $salesOrderId =
            DB::table('sales_orders')
                ->insertGetId([
                    'so_no' =>
                        'SO-SI-001',

                    'customer_id' =>
                        $customerId,

                    'order_date' =>
                        '2026-08-08',

                    'delivery_date' =>
                        '2026-08-08',

                    'status' =>
                        'COMPLETED',

                    'remarks' =>
                        'Sales Invoice test SO',

                    'created_by' =>
                        $this->data['user_id'],

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        /*
        |--------------------------------------------------------------------------
        | Sales Order Detail
        |--------------------------------------------------------------------------
        */

        $salesOrderDetailId =
            DB::table('sales_order_details')
                ->insertGetId([
                    'sales_order_id' =>
                        $salesOrderId,

                    'item_id' =>
                        $this->data['item_id'],

                    'qty' =>
                        10,

                    'unit_price' =>
                        8000,

                    'discount' =>
                        0,

                    'delivered_qty' =>
                        10,

                    'remarks' =>
                        'Sales Invoice test SO line',

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        /*
        |--------------------------------------------------------------------------
        | Delivery Order
        |--------------------------------------------------------------------------
        */

        $deliveryOrderId =
            DB::table('delivery_orders')
                ->insertGetId([
                    'do_no' =>
                        'DO-SI-001',

                    'sales_order_id' =>
                        $salesOrderId,

                    'delivery_date' =>
                        '2026-08-08',

                    'status' =>
                        'POSTED',

                    'remarks' =>
                        'Sales Invoice test DO',

                    'created_by' =>
                        $this->data['user_id'],

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        DB::table('delivery_order_details')
            ->insert([
                'delivery_order_id' =>
                    $deliveryOrderId,

                'sales_order_detail_id' =>
                    $salesOrderDetailId,

                'item_id' =>
                    $this->data['item_id'],

                'qty' =>
                    10,

                'unit_cost' =>
                    6000,

                'remarks' =>
                    'Sales Invoice test DO line',

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]);

        /*
        |--------------------------------------------------------------------------
        | Create Sales Invoice
        |--------------------------------------------------------------------------
        */

        $invoice =
            app(SalesInvoiceService::class)
                ->create(
                    new SalesInvoiceDTO(
                        customerId:
                            $customerId,

                        deliveryOrderId:
                            $deliveryOrderId,

                        dueDate:
                            '2026-09-08',

                        remarks:
                            'Dynamic AR mapping test',

                        createdBy:
                            $this->data['user_id'],

                        lines: [
                            new SalesInvoiceLineDTO(
                                itemId:
                                    $this->data['item_id'],

                                qty:
                                    10,

                                unitPrice:
                                    8000,

                                discount:
                                    0,

                                remarks:
                                    'Sales Invoice test line',
                            ),
                        ],
                    )
                );

        /*
        |--------------------------------------------------------------------------
        | Invoice
        |--------------------------------------------------------------------------
        */

        $this->assertEqualsWithDelta(
            80000,
            (float) $invoice->grand_total,
            0.01
        );

        /*
        |--------------------------------------------------------------------------
        | Account Receivable
        |--------------------------------------------------------------------------
        */

        $receivable =
            DB::table('account_receivables')
                ->where(
                    'sales_invoice_id',
                    $invoice->id
                )
                ->first();

        $this->assertNotNull(
            $receivable
        );

        $this->assertEqualsWithDelta(
            80000,
            (float) $receivable->amount,
            0.01
        );

        $this->assertEqualsWithDelta(
            80000,
            (float) $receivable->balance_amount,
            0.01
        );

        /*
        |--------------------------------------------------------------------------
        | Journal
        |--------------------------------------------------------------------------
        */

        $journal =
            Journal::query()
                ->where(
                    'reference_type',
                    'SALES_INVOICE'
                )
                ->where(
                    'reference_id',
                    $invoice->id
                )
                ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Dynamic AR — Debit
        |--------------------------------------------------------------------------
        */

        $arLine =
            DB::table('journal_details')
                ->where(
                    'journal_id',
                    $journal->id
                )
                ->where(
                    'account_id',
                    $alternativeArAccountId
                )
                ->first();

        $this->assertNotNull(
            $arLine,
            'Sales Invoice must use the company AR account mapping.'
        );

        $this->assertEqualsWithDelta(
            80000,
            (float) $arLine->debit,
            0.01
        );

        $this->assertEqualsWithDelta(
            0,
            (float) $arLine->credit,
            0.01
        );

        /*
        |--------------------------------------------------------------------------
        | Sales Revenue — Credit
        |--------------------------------------------------------------------------
        */

        $salesLine =
            DB::table('journal_details')
                ->where(
                    'journal_id',
                    $journal->id
                )
                ->where(
                    'account_id',
                    $salesAccountId
                )
                ->first();

        $this->assertNotNull(
            $salesLine
        );

        $this->assertEqualsWithDelta(
            0,
            (float) $salesLine->debit,
            0.01
        );

        $this->assertEqualsWithDelta(
            80000,
            (float) $salesLine->credit,
            0.01
        );

        /*
        |--------------------------------------------------------------------------
        | Balanced
        |--------------------------------------------------------------------------
        */

        $journal->load('details');

        $this->assertEqualsWithDelta(
            80000,
            (float) $journal->details->sum(
                'debit'
            ),
            0.01
        );

        $this->assertEqualsWithDelta(
            80000,
            (float) $journal->details->sum(
                'credit'
            ),
            0.01
        );

        /*
        |--------------------------------------------------------------------------
        | Delivery Order
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseHas(
            'delivery_orders',
            [
                'id' =>
                    $deliveryOrderId,

                'status' =>
                    'INVOICED',
            ]
        );
    }

    public function test_multiline_sales_invoice_uses_each_item_sales_account(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Account Groups
        |--------------------------------------------------------------------------
        */

        $revenueGroupId =
            DB::table('account_groups')
                ->insertGetId([
                    'code' =>
                        'REV-SI-ML',

                    'name' =>
                        'Revenue Multiline Sales Invoice',

                    'is_active' =>
                        true,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        /*
        |--------------------------------------------------------------------------
        | Sales Accounts
        |--------------------------------------------------------------------------
        */

        $salesAccountAId =
            DB::table('accounts')
                ->insertGetId([
                    'account_group_id' =>
                        $revenueGroupId,

                    'code' =>
                        '4101-SI-A',

                    'name' =>
                        'Sales Revenue A',

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

        $salesAccountBId =
            DB::table('accounts')
                ->insertGetId([
                    'account_group_id' =>
                        $revenueGroupId,

                    'code' =>
                        '4102-SI-B',

                    'name' =>
                        'Sales Revenue B',

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
        | Category A
        |--------------------------------------------------------------------------
        */

        $itemA =
            DB::table('items')
                ->where(
                    'id',
                    $this->data['item_id']
                )
                ->first();

        $this->assertNotNull(
            $itemA
        );

        DB::table('item_categories')
            ->where(
                'id',
                $itemA->item_category_id
            )
            ->update([
                'sales_account_id' =>
                    $salesAccountAId,

                'updated_at' =>
                    now(),
            ]);

        /*
        |--------------------------------------------------------------------------
        | Category B
        |--------------------------------------------------------------------------
        */

        $categoryBId =
            DB::table('item_categories')
                ->insertGetId([
                    'code' =>
                        'CAT-SI-B',

                    'name' =>
                        'Category Sales Invoice B',

                    'description' =>
                        'Multiline Sales Invoice category B',

                    'is_active' =>
                        true,

                    'inventory_account_id' =>
                        $this->data['inventory_account_id'],

                    'cogs_account_id' =>
                        $this->data['cogs_account_id'],

                    'sales_account_id' =>
                        $salesAccountBId,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        $itemBId =
            DB::table('items')
                ->insertGetId([
                    'item_category_id' =>
                        $categoryBId,

                    'uom_id' =>
                        $itemA->uom_id,

                    'code' =>
                        'ITEM-SI-B',

                    'name' =>
                        'Item Sales Invoice B',

                    'description' =>
                        'Multiline Sales Invoice Item B',

                    'minimum_stock' =>
                        0,

                    'maximum_stock' =>
                        0,

                    'average_cost' =>
                        0,

                    'last_purchase_price' =>
                        0,

                    'is_active' =>
                        true,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        /*
        |--------------------------------------------------------------------------
        | Customer
        |--------------------------------------------------------------------------
        */

        $customerId =
            DB::table('customers')
                ->insertGetId([
                    'code' =>
                        'CUS-SI-ML',

                    'name' =>
                        'Customer Multiline Sales Invoice',

                    'credit_limit' =>
                        1000000,

                    'credit_days' =>
                        30,

                    'is_active' =>
                        true,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        /*
        |--------------------------------------------------------------------------
        | Sales Order
        |--------------------------------------------------------------------------
        */

        $salesOrderId =
            DB::table('sales_orders')
                ->insertGetId([
                    'so_no' =>
                        'SO-SI-ML',

                    'customer_id' =>
                        $customerId,

                    'order_date' =>
                        '2026-08-08',

                    'delivery_date' =>
                        '2026-08-08',

                    'status' =>
                        'COMPLETED',

                    'remarks' =>
                        'Multiline Sales Invoice SO',

                    'created_by' =>
                        $this->data['user_id'],

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        $soDetailAId =
            DB::table('sales_order_details')
                ->insertGetId([
                    'sales_order_id' =>
                        $salesOrderId,

                    'item_id' =>
                        $this->data['item_id'],

                    'qty' =>
                        10,

                    'unit_price' =>
                        8000,

                    'discount' =>
                        0,

                    'delivered_qty' =>
                        10,

                    'remarks' =>
                        'SO line A',

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        $soDetailBId =
            DB::table('sales_order_details')
                ->insertGetId([
                    'sales_order_id' =>
                        $salesOrderId,

                    'item_id' =>
                        $itemBId,

                    'qty' =>
                        3,

                    'unit_price' =>
                        10000,

                    'discount' =>
                        0,

                    'delivered_qty' =>
                        3,

                    'remarks' =>
                        'SO line B',

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        /*
        |--------------------------------------------------------------------------
        | Delivery Order
        |--------------------------------------------------------------------------
        */

        $deliveryOrderId =
            DB::table('delivery_orders')
                ->insertGetId([
                    'do_no' =>
                        'DO-SI-ML',

                    'sales_order_id' =>
                        $salesOrderId,

                    'delivery_date' =>
                        '2026-08-08',

                    'status' =>
                        'POSTED',

                    'remarks' =>
                        'Multiline Sales Invoice DO',

                    'created_by' =>
                        $this->data['user_id'],

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        DB::table('delivery_order_details')
            ->insert([
                [
                    'delivery_order_id' =>
                        $deliveryOrderId,

                    'sales_order_detail_id' =>
                        $soDetailAId,

                    'item_id' =>
                        $this->data['item_id'],

                    'qty' =>
                        10,

                    'unit_cost' =>
                        6000,

                    'remarks' =>
                        'DO line A',

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ],

                [
                    'delivery_order_id' =>
                        $deliveryOrderId,

                    'sales_order_detail_id' =>
                        $soDetailBId,

                    'item_id' =>
                        $itemBId,

                    'qty' =>
                        3,

                    'unit_cost' =>
                        5000,

                    'remarks' =>
                        'DO line B',

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ],
            ]);

        /*
        |--------------------------------------------------------------------------
        | Create Sales Invoice
        |--------------------------------------------------------------------------
        */

        $invoice =
            app(SalesInvoiceService::class)
                ->create(
                    new SalesInvoiceDTO(
                        customerId:
                            $customerId,

                        deliveryOrderId:
                            $deliveryOrderId,

                        dueDate:
                            '2026-09-08',

                        remarks:
                            'Multiline Sales Invoice test',

                        createdBy:
                            $this->data['user_id'],

                        lines: [
                            new SalesInvoiceLineDTO(
                                itemId:
                                    $this->data['item_id'],

                                qty:
                                    10,

                                unitPrice:
                                    8000,

                                discount:
                                    0,

                                remarks:
                                    'Invoice line A',
                            ),

                            new SalesInvoiceLineDTO(
                                itemId:
                                    $itemBId,

                                qty:
                                    3,

                                unitPrice:
                                    10000,

                                discount:
                                    0,

                                remarks:
                                    'Invoice line B',
                            ),
                        ],
                    )
                );

        /*
        |--------------------------------------------------------------------------
        | Invoice Total
        |--------------------------------------------------------------------------
        |
        | A = 10 x 8,000  = 80,000
        | B = 3 x 10,000  = 30,000
        | Total           = 110,000
        |
        */

        $this->assertEqualsWithDelta(
            110000,
            (float) $invoice->grand_total,
            0.01
        );

        /*
        |--------------------------------------------------------------------------
        | Journal
        |--------------------------------------------------------------------------
        */

        $journal =
            Journal::query()
                ->where(
                    'reference_type',
                    'SALES_INVOICE'
                )
                ->where(
                    'reference_id',
                    $invoice->id
                )
                ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Revenue A
        |--------------------------------------------------------------------------
        */

        $salesLineA =
            DB::table('journal_details')
                ->where(
                    'journal_id',
                    $journal->id
                )
                ->where(
                    'account_id',
                    $salesAccountAId
                )
                ->first();

        $this->assertNotNull(
            $salesLineA,
            'Multiline Sales Invoice must post revenue to sales account A.'
        );

        $this->assertEqualsWithDelta(
            80000,
            (float) $salesLineA->credit,
            0.01
        );

        /*
        |--------------------------------------------------------------------------
        | Revenue B
        |--------------------------------------------------------------------------
        */

        $salesLineB =
            DB::table('journal_details')
                ->where(
                    'journal_id',
                    $journal->id
                )
                ->where(
                    'account_id',
                    $salesAccountBId
                )
                ->first();

        $this->assertNotNull(
            $salesLineB,
            'Multiline Sales Invoice must post revenue to sales account B.'
        );

        $this->assertEqualsWithDelta(
            30000,
            (float) $salesLineB->credit,
            0.01
        );

        /*
        |--------------------------------------------------------------------------
        | Balanced
        |--------------------------------------------------------------------------
        */

        $journal->load('details');

        $this->assertEqualsWithDelta(
            110000,
            (float) $journal->details->sum(
                'debit'
            ),
            0.01
        );

        $this->assertEqualsWithDelta(
            110000,
            (float) $journal->details->sum(
                'credit'
            ),
            0.01
        );
    }

    public function test_sales_invoice_rolls_back_when_company_accounting_mapping_is_missing(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Revenue Group
        |--------------------------------------------------------------------------
        */

        $revenueGroupId =
            DB::table('account_groups')
                ->insertGetId([
                    'code' =>
                        'REV-SI-RB',

                    'name' =>
                        'Revenue Sales Invoice Rollback Test',

                    'is_active' =>
                        true,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        /*
        |--------------------------------------------------------------------------
        | Sales Account
        |--------------------------------------------------------------------------
        */

        $salesAccountId =
            DB::table('accounts')
                ->insertGetId([
                    'account_group_id' =>
                        $revenueGroupId,

                    'code' =>
                        '4199-SI-RB',

                    'name' =>
                        'Sales Revenue Rollback Test',

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
        | Configure Item Sales Account
        |--------------------------------------------------------------------------
        */

        $item =
            DB::table('items')
                ->where(
                    'id',
                    $this->data['item_id']
                )
                ->first();

        $this->assertNotNull(
            $item
        );

        DB::table('item_categories')
            ->where(
                'id',
                $item->item_category_id
            )
            ->update([
                'sales_account_id' =>
                    $salesAccountId,

                'updated_at' =>
                    now(),
            ]);

        /*
        |--------------------------------------------------------------------------
        | Customer
        |--------------------------------------------------------------------------
        */

        $customerId =
            DB::table('customers')
                ->insertGetId([
                    'code' =>
                        'CUS-SI-RB',

                    'name' =>
                        'Customer Sales Invoice Rollback',

                    'credit_limit' =>
                        1000000,

                    'credit_days' =>
                        30,

                    'is_active' =>
                        true,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        /*
        |--------------------------------------------------------------------------
        | Sales Order
        |--------------------------------------------------------------------------
        */

        $salesOrderId =
            DB::table('sales_orders')
                ->insertGetId([
                    'so_no' =>
                        'SO-SI-RB',

                    'customer_id' =>
                        $customerId,

                    'order_date' =>
                        '2026-08-09',

                    'delivery_date' =>
                        '2026-08-09',

                    'status' =>
                        'COMPLETED',

                    'remarks' =>
                        'Sales Invoice rollback SO',

                    'created_by' =>
                        $this->data['user_id'],

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        $salesOrderDetailId =
            DB::table('sales_order_details')
                ->insertGetId([
                    'sales_order_id' =>
                        $salesOrderId,

                    'item_id' =>
                        $this->data['item_id'],

                    'qty' =>
                        5,

                    'unit_price' =>
                        9000,

                    'discount' =>
                        0,

                    'delivered_qty' =>
                        5,

                    'remarks' =>
                        'Rollback SO detail',

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        /*
        |--------------------------------------------------------------------------
        | Delivery Order
        |--------------------------------------------------------------------------
        */

        $deliveryOrderId =
            DB::table('delivery_orders')
                ->insertGetId([
                    'do_no' =>
                        'DO-SI-RB',

                    'sales_order_id' =>
                        $salesOrderId,

                    'delivery_date' =>
                        '2026-08-09',

                    'status' =>
                        'POSTED',

                    'remarks' =>
                        'Sales Invoice rollback DO',

                    'created_by' =>
                        $this->data['user_id'],

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        DB::table('delivery_order_details')
            ->insert([
                'delivery_order_id' =>
                    $deliveryOrderId,

                'sales_order_detail_id' =>
                    $salesOrderDetailId,

                'item_id' =>
                    $this->data['item_id'],

                'qty' =>
                    5,

                'unit_cost' =>
                    6000,

                'remarks' =>
                    'Rollback DO detail',

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]);

        /*
        |--------------------------------------------------------------------------
        | Remove Company Accounting Mapping
        |--------------------------------------------------------------------------
        */

        DB::table('accounting_account_mappings')
            ->where(
                'company_id',
                $this->data['company_id']
            )
            ->delete();

        /*
        |--------------------------------------------------------------------------
        | Before State
        |--------------------------------------------------------------------------
        */

        $invoiceCountBefore =
            DB::table('sales_invoices')
                ->count();

        $invoiceDetailCountBefore =
            DB::table('sales_invoice_details')
                ->count();

        $receivableCountBefore =
            DB::table('account_receivables')
                ->count();

        $journalCountBefore =
            DB::table('journals')
                ->count();

        /*
        |--------------------------------------------------------------------------
        | Execute
        |--------------------------------------------------------------------------
        */

        try {

            app(SalesInvoiceService::class)
                ->create(
                    new SalesInvoiceDTO(
                        customerId:
                            $customerId,

                        deliveryOrderId:
                            $deliveryOrderId,

                        dueDate:
                            '2026-09-09',

                        remarks:
                            'Sales Invoice rollback test',

                        createdBy:
                            $this->data['user_id'],

                        lines: [
                            new SalesInvoiceLineDTO(
                                itemId:
                                    $this->data['item_id'],

                                qty:
                                    5,

                                unitPrice:
                                    9000,

                                discount:
                                    0,

                                remarks:
                                    'Rollback invoice line',
                            ),
                        ],
                    )
                );

            $this->fail(
                'Sales Invoice must fail when company accounting mapping is missing.'
            );

        } catch (\RuntimeException $e) {

            $this->assertSame(
                sprintf(
                    'Accounting account mapping is not configured for company %d.',
                    $this->data['company_id']
                ),
                $e->getMessage()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Verify Rollback
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $invoiceCountBefore,
            DB::table('sales_invoices')
                ->count()
        );

        $this->assertSame(
            $invoiceDetailCountBefore,
            DB::table('sales_invoice_details')
                ->count()
        );

        $this->assertSame(
            $receivableCountBefore,
            DB::table('account_receivables')
                ->count()
        );

        $this->assertSame(
            $journalCountBefore,
            DB::table('journals')
                ->count()
        );

        /*
        |--------------------------------------------------------------------------
        | Delivery Order Must Remain POSTED
        |--------------------------------------------------------------------------
        */

        $deliveryOrderStatus =
            DB::table('delivery_orders')
                ->where(
                    'id',
                    $deliveryOrderId
                )
                ->value(
                    'status'
                );

        $this->assertSame(
            'POSTED',
            $deliveryOrderStatus
        );

        /*
        |--------------------------------------------------------------------------
        | No Invoice For Delivery Order
        |--------------------------------------------------------------------------
        */

        $this->assertFalse(
            DB::table('sales_invoices')
                ->where(
                    'delivery_order_id',
                    $deliveryOrderId
                )
                ->exists()
        );

        /*
        |--------------------------------------------------------------------------
        | No AR Created
        |--------------------------------------------------------------------------
        */

        $this->assertFalse(
            DB::table('account_receivables')
                ->where(
                    'customer_id',
                    $customerId
                )
                ->exists()
        );
    }
}