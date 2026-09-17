<?php

namespace Tests\Feature;

use App\DTO\DeliveryOrderDTO;
use App\DTO\DeliveryOrderLineDTO;
use App\DTO\SalesOrderDTO;
use App\DTO\SalesOrderLineDTO;
use App\Models\Branch;
use App\Models\Company;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\DeliveryOrderService;
use App\Services\SalesOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;
use App\Models\Account;
use App\Models\Item;
use App\DTO\SalesInvoiceDTO;
use App\DTO\SalesInvoiceLineDTO;
use App\Models\DeliveryOrder;
use App\Services\SalesInvoiceService;

class SalesCycleCompanyOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    protected function setUp(): void
    {
        parent::setUp();

        $this->data =
            InventoryReconciliationTestData::create();

        $this->createDocumentSequence(
            documentType: 'SO',
            prefix: 'SO'
        );

        $this->createDocumentSequence(
            documentType: 'DO',
            prefix: 'DO'
        );

        DB::table('document_sequences')
        ->updateOrInsert(
            [
                'document_type' =>
                    'CR',
            ],
            [
                'prefix' =>
                    'CR',

                'description' =>
                    'Customer Receipt Ownership Test Sequence',

                'current_number' =>
                    0,

                'padding' =>
                    5,

                'is_active' =>
                    true,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]
        );
    }

    public function test_sales_order_inherits_company_from_creator(): void
    {
        DB::statement(
            'SET FOREIGN_KEY_CHECKS=0'
        );

        try {

            $salesOrder =
                app(SalesOrderService::class)
                    ->create(
                        new SalesOrderDTO(
                            customerId:
                                999999,

                            deliveryDate:
                                '2026-08-08',

                            remarks:
                                'SO company ownership test',

                            createdBy:
                                $this->data['user_id'],

                            lines: [
                                new SalesOrderLineDTO(
                                    itemId:
                                        $this->data['item_id'],

                                    qty:
                                        10,

                                    unitPrice:
                                        10000,

                                    discount:
                                        0,

                                    remarks:
                                        'SO ownership line',
                                ),
                            ],
                        )
                    );

        } finally {

            DB::statement(
                'SET FOREIGN_KEY_CHECKS=1'
            );
        }

        $this->assertSame(
            (int) $this->data['company_id'],
            (int) $salesOrder->company_id
        );

        $this->assertDatabaseHas(
            'sales_orders',
            [
                'id' =>
                    $salesOrder->id,

                'company_id' =>
                    $this->data['company_id'],
            ]
        );
    }

    public function test_delivery_order_rejects_creator_from_different_company(): void
    {
        [
            $salesOrder,
            $salesOrderDetail,
        ] =
            $this->createSalesOrder();

        [
            $companyBId,
            $userBId,
        ] =
            $this->createSecondCompany();

        $this->assertNotSame(
            (int) $companyBId,
            (int) $salesOrder->company_id
        );

        $beforeDeliveryOrders =
            DB::table('delivery_orders')
                ->count();

        $beforeDetails =
            DB::table('delivery_order_details')
                ->count();

        $beforeLedgers =
            DB::table('stock_ledgers')
                ->count();

        $beforeJournals =
            DB::table('journals')
                ->count();

        $beforeDeliveredQty =
            (float) $salesOrderDetail->delivered_qty;

        try {

            app(DeliveryOrderService::class)
                ->create(
                    new DeliveryOrderDTO(
                        salesOrderId:
                            $salesOrder->id,

                        remarks:
                            'Cross-company actor DO test',

                        warehouseId:
                            $this->data['warehouse_id'],

                        createdBy:
                            $userBId,

                        lines: [
                            new DeliveryOrderLineDTO(
                                itemId:
                                    $this->data['item_id'],

                                qty:
                                    1,

                                salesOrderDetailId:
                                    $salesOrderDetail->id,

                                remarks:
                                    'Must reject foreign actor',
                            ),
                        ],

                        deliveryDate:
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

        $this->assertSame(
            $beforeDeliveryOrders,
            DB::table('delivery_orders')
                ->count()
        );

        $this->assertSame(
            $beforeDetails,
            DB::table('delivery_order_details')
                ->count()
        );

        $this->assertSame(
            $beforeLedgers,
            DB::table('stock_ledgers')
                ->count()
        );

        $this->assertSame(
            $beforeJournals,
            DB::table('journals')
                ->count()
        );

        $salesOrderDetail->refresh();

        $this->assertEqualsWithDelta(
            $beforeDeliveredQty,
            (float) $salesOrderDetail->delivered_qty,
            0.0001
        );
    }

    public function test_delivery_order_rejects_warehouse_from_different_company(): void
    {
        [
            $salesOrder,
            $salesOrderDetail,
        ] =
            $this->createSalesOrder();

        [
            $companyBId,
            $userBId,
            $warehouseBId,
        ] =
            $this->createSecondCompany(
                createWarehouse: true
            );

        $beforeDeliveryOrders =
            DB::table(
                'delivery_orders'
            )->count();

        $beforeDetails =
            DB::table(
                'delivery_order_details'
            )->count();

        $beforeLedgers =
            DB::table(
                'stock_ledgers'
            )->count();

        $beforeJournals =
            DB::table(
                'journals'
            )->count();

        try {

            app(DeliveryOrderService::class)
                ->create(
                    new DeliveryOrderDTO(
                        salesOrderId:
                            $salesOrder->id,

                        remarks:
                            'Cross company warehouse test',

                        warehouseId:
                            $warehouseBId,

                        createdBy:
                            $this->data['user_id'],

                        lines: [
                            new DeliveryOrderLineDTO(
                                itemId:
                                    $this->data['item_id'],

                                qty:
                                    1,

                                salesOrderDetailId:
                                    $salesOrderDetail->id,

                                remarks:
                                    null,
                            ),
                        ],

                        deliveryDate:
                            '2026-08-08',
                    )
                );

            $this->fail(
                'Expected cross-company warehouse exception was not thrown.'
            );

        } catch (RuntimeException $exception) {

            $this->assertSame(
                'Delivery order warehouse does not belong to sales order company.',
                $exception->getMessage()
            );
        }

        $this->assertNotSame(
            (int) $companyBId,
            (int) $salesOrder->company_id
        );

        $this->assertSame(
            $beforeDeliveryOrders,
            DB::table(
                'delivery_orders'
            )->count()
        );

        $this->assertSame(
            $beforeDetails,
            DB::table(
                'delivery_order_details'
            )->count()
        );

        $this->assertSame(
            $beforeLedgers,
            DB::table(
                'stock_ledgers'
            )->count()
        );

        $this->assertSame(
            $beforeJournals,
            DB::table(
                'journals'
            )->count()
        );

        $salesOrderDetail->refresh();

        $this->assertEqualsWithDelta(
            0,
            (float)
            $salesOrderDetail->delivered_qty,
            0.0001
        );
    }

    public function test_sales_invoice_rejects_creator_from_different_company(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Delivery Order — Company A
        |--------------------------------------------------------------------------
        */

        DB::statement(
            'SET FOREIGN_KEY_CHECKS=0'
        );

        try {

            $deliveryOrder =
                DeliveryOrder::create([
                    'company_id' =>
                        $this->data['company_id'],

                    'do_no' =>
                        'DO-SI-GUARD-' . uniqid(),

                    'sales_order_id' =>
                        999999,

                    'delivery_date' =>
                        '2026-08-08',

                    'status' =>
                        'POSTED',

                    'remarks' =>
                        'Sales invoice cross-company guard fixture',

                    'created_by' =>
                        $this->data['user_id'],
                ]);

        } finally {

            DB::statement(
                'SET FOREIGN_KEY_CHECKS=1'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Company B Actor
        |--------------------------------------------------------------------------
        */

        [
            $companyBId,
            $userBId,
        ] =
            $this->createSecondCompany();

        $this->assertNotSame(
            (int) $companyBId,
            (int) $deliveryOrder->company_id
        );

        /*
        |--------------------------------------------------------------------------
        | Sales Account
        |--------------------------------------------------------------------------
        |
        | Tetap kita siapkan agar sebelum guard production dibuat,
        | service lama mampu berjalan sampai selesai.
        | Dengan begitu RED benar-benar membuktikan actor guard belum ada.
        |
        */

        $revenueGroupId =
            DB::table('account_groups')
                ->insertGetId([
                    'code' => 'REV-T',
                    'name' => 'Revenue SI Guard Test',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

        $salesAccount =
            Account::create([
                'code' => '4098',
                'name' => 'Sales Revenue SI Guard Test',
                'account_group_id' => $revenueGroupId,
                'is_header' => false,
                'is_active' => true,
            ]);

        $item =
            Item::query()
                ->with('category')
                ->findOrFail(
                    $this->data['item_id']
                );

        $item->category->update([
            'sales_account_id' =>
                $salesAccount->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Invoice Sequence
        |--------------------------------------------------------------------------
        */

        $this->createDocumentSequence(
            documentType: 'INV',
            prefix: 'INV'
        );

        $beforeInvoices =
            DB::table('sales_invoices')
                ->count();

        $beforeInvoiceDetails =
            DB::table('sales_invoice_details')
                ->count();

        $beforeReceivables =
            DB::table('account_receivables')
                ->count();

        $beforeJournals =
            DB::table('journals')
                ->count();

        /*
        |--------------------------------------------------------------------------
        | Attempt With Company B Actor
        |--------------------------------------------------------------------------
        */

        DB::statement(
            'SET FOREIGN_KEY_CHECKS=0'
        );

        try {

            try {

                app(SalesInvoiceService::class)
                    ->create(
                        new SalesInvoiceDTO(
                            customerId:
                                999999,

                            deliveryOrderId:
                                $deliveryOrder->id,

                            dueDate:
                                '2026-09-08',

                            remarks:
                                'Cross-company actor SI test',

                            createdBy:
                                $userBId,

                            lines: [
                                new SalesInvoiceLineDTO(
                                    itemId:
                                        $this->data['item_id'],

                                    qty:
                                        1,

                                    unitPrice:
                                        10000,

                                    discount:
                                        0,

                                    remarks:
                                        'Must reject foreign actor',
                                ),
                            ],
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

        } finally {

            DB::statement(
                'SET FOREIGN_KEY_CHECKS=1'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | No Side Effects
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $beforeInvoices,
            DB::table('sales_invoices')
                ->count()
        );

        $this->assertSame(
            $beforeInvoiceDetails,
            DB::table('sales_invoice_details')
                ->count()
        );

        $this->assertSame(
            $beforeReceivables,
            DB::table('account_receivables')
                ->count()
        );

        $this->assertSame(
            $beforeJournals,
            DB::table('journals')
                ->count()
        );

        $deliveryOrder->refresh();

        $this->assertSame(
            'POSTED',
            $deliveryOrder->status
        );
    }

    private function createSalesOrder(): array
    {
        DB::statement(
            'SET FOREIGN_KEY_CHECKS=0'
        );

        try {

            $salesOrder =
                SalesOrder::create([
                    'company_id' =>
                        $this->data['company_id'],

                    'so_no' =>
                        'SO-OWN-' . uniqid(),

                    'customer_id' =>
                        999999,

                    'order_date' =>
                        '2026-08-08',

                    'delivery_date' =>
                        '2026-08-08',

                    'status' =>
                        'APPROVED',

                    'remarks' =>
                        'Sales company ownership fixture',

                    'created_by' =>
                        $this->data['user_id'],
                ]);

        } finally {

            DB::statement(
                'SET FOREIGN_KEY_CHECKS=1'
            );
        }

        $detail =
            $salesOrder
                ->details()
                ->create([
                    'item_id' =>
                        $this->data['item_id'],

                    'qty' =>
                        10,

                    'unit_price' =>
                        10000,

                    'discount' =>
                        0,

                    'delivered_qty' =>
                        0,

                    'remarks' =>
                        'Sales ownership detail',
                ]);

        return [
            $salesOrder,
            $detail,
        ];
    }

    private function createSecondCompany(
        bool $createWarehouse = false
    ): array {

        $company =
            Company::create([
                'code' =>
                    'SCB' . substr(
                        uniqid(),
                        -5
                    ),

                'name' =>
                    'Sales Company B',

                'is_active' =>
                    true,
            ]);

        $branch =
            Branch::create([
                'company_id' =>
                    $company->id,

                'code' =>
                    'SB' . substr(
                        uniqid(),
                        -5
                    ),

                'name' =>
                    'Sales Branch B',

                'is_active' =>
                    true,
            ]);

        $user =
            User::create([
                'company_id' =>
                    $company->id,

                'branch_id' =>
                    $branch->id,

                'name' =>
                    'Sales User B',

                'email' =>
                    'sales-b-'
                    .
                    uniqid()
                    .
                    '@example.test',

                'password' =>
                    'password',

                'is_active' =>
                    true,
            ]);

        if (!$createWarehouse) {
            return [
                $company->id,
                $user->id,
            ];
        }

        $warehouseId =
            DB::table(
                'warehouses'
            )->insertGetId([
                'company_id' =>
                    $company->id,

                'branch_id' =>
                    $branch->id,

                'code' =>
                    'SW' . substr(
                        uniqid(),
                        -5
                    ),

                'name' =>
                    'Sales Warehouse B',

                'is_active' =>
                    true,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]);

        return [
            $company->id,
            $user->id,
            $warehouseId,
        ];
    }

    private function createDocumentSequence(
        string $documentType,
        string $prefix
    ): void {

        DB::table(
            'document_sequences'
        )->insert([
            'document_type' =>
                $documentType,

            'prefix' =>
                $prefix,

            'description' =>
                $documentType
                .
                ' Company Ownership Test',

            'current_number' =>
                0,

            'padding' =>
                5,

            'is_active' =>
                true,

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);
    }

    public function test_customer_receipt_rejects_creator_from_different_company(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Company A
        |--------------------------------------------------------------------------
        */

        $companyAId =
            (int) $this->data['company_id'];

        /*
        |--------------------------------------------------------------------------
        | Company B + User B
        |--------------------------------------------------------------------------
        */

        [
            $companyBId,
            $userBId,
        ] =
            $this->createSecondCompany();

        $this->assertNotSame(
            $companyAId,
            (int) $companyBId
        );

        /*
        |--------------------------------------------------------------------------
        | Customer
        |--------------------------------------------------------------------------
        */

        $customerId =
            DB::table('customers')
                ->insertGetId([
                    'code' =>
                        'CUS-CR-GUARD',

                    'name' =>
                        'Customer Receipt Guard',

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
        | Sales Invoice — Company A
        |--------------------------------------------------------------------------
        */

        $salesInvoiceId =
            DB::table('sales_invoices')
                ->insertGetId([
                    'company_id' =>
                        $companyAId,

                    'invoice_no' =>
                        'INV-CR-GUARD',

                    'customer_id' =>
                        $customerId,

                    'delivery_order_id' =>
                        999991,

                    'invoice_date' =>
                        '2026-08-20',

                    'due_date' =>
                        '2026-09-20',

                    'subtotal' =>
                        100000,

                    'discount_amount' =>
                        0,

                    'tax_amount' =>
                        0,

                    'grand_total' =>
                        100000,

                    'status' =>
                        'POSTED',

                    'remarks' =>
                        'Customer Receipt guard source invoice',

                    'created_by' =>
                        $this->data['user_id'],

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        /*
        |--------------------------------------------------------------------------
        | Account Receivable — Company A
        |--------------------------------------------------------------------------
        */

        $accountReceivableId =
            DB::table('account_receivables')
                ->insertGetId([
                    'company_id' =>
                        $companyAId,

                    'customer_id' =>
                        $customerId,

                    'sales_invoice_id' =>
                        $salesInvoiceId,

                    'invoice_date' =>
                        '2026-08-20',

                    'due_date' =>
                        '2026-09-20',

                    'amount' =>
                        100000,

                    'paid_amount' =>
                        0,

                    'balance_amount' =>
                        100000,

                    'status' =>
                        'OPEN',

                    'remarks' =>
                        'Customer Receipt guard AR',

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        /*
        |--------------------------------------------------------------------------
        | Cash / Bank Account
        |--------------------------------------------------------------------------
        */

        $assetGroupId =
            DB::table('account_groups')
                ->where(
                    'code',
                    'AST-T'
                )
                ->value(
                    'id'
                );

        $this->assertNotNull(
            $assetGroupId
        );

        $bankAccountId =
            DB::table('accounts')
                ->insertGetId([
                    'account_group_id' =>
                        $assetGroupId,

                    'code' =>
                        '1014-CR-GUARD',

                    'name' =>
                        'Customer Receipt Guard Bank',

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
        | Snapshot Before Attempt
        |--------------------------------------------------------------------------
        */

        $beforeReceipts =
            DB::table('customer_receipts')
                ->count();

        $beforeJournals =
            DB::table('journals')
                ->count();

        $arBefore =
            DB::table('account_receivables')
                ->where(
                    'id',
                    $accountReceivableId
                )
                ->first();

        /*
        |--------------------------------------------------------------------------
        | Attempt — Actor Company B, AR Company A
        |--------------------------------------------------------------------------
        */

        try {

            app(\App\Services\CustomerReceiptService::class)
                ->create(
                    new \App\DTO\CustomerReceiptDTO(
                        customerId:
                            $customerId,

                        accountReceivableId:
                            $accountReceivableId,

                        cashBankAccountId:
                            $bankAccountId,

                        amount:
                            40000,

                        remarks:
                            'Cross-company actor CR test',

                        createdBy:
                            $userBId,
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

        /*
        |--------------------------------------------------------------------------
        | No Side Effects
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $beforeReceipts,
            DB::table('customer_receipts')
                ->count()
        );

        $this->assertSame(
            $beforeJournals,
            DB::table('journals')
                ->count()
        );

        $arAfter =
            DB::table('account_receivables')
                ->where(
                    'id',
                    $accountReceivableId
                )
                ->first();

        $this->assertEqualsWithDelta(
            (float) $arBefore->paid_amount,
            (float) $arAfter->paid_amount,
            0.01
        );

        $this->assertEqualsWithDelta(
            (float) $arBefore->balance_amount,
            (float) $arAfter->balance_amount,
            0.01
        );

        $this->assertSame(
            $arBefore->status,
            $arAfter->status
        );
    }
}