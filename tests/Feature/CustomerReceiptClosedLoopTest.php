<?php

namespace Tests\Feature;

use App\DTO\CustomerReceiptDTO;
use App\Models\Journal;
use App\Services\CustomerReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;

class CustomerReceiptClosedLoopTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    protected function setUp(): void
    {
        parent::setUp();

        /*
        |--------------------------------------------------------------------------
        | Base Fixture
        |--------------------------------------------------------------------------
        */

        $this->data =
            InventoryReconciliationTestData::create();

        /*
        |--------------------------------------------------------------------------
        | Customer Receipt Sequence
        |--------------------------------------------------------------------------
        */

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
                        'Customer Receipt Test Sequence',

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

    public function test_customer_receipt_uses_company_ar_mapping_and_selected_cash_bank_account(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Asset Group
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

        /*
        |--------------------------------------------------------------------------
        | Alternative AR Account
        |--------------------------------------------------------------------------
        |
        | This is intentionally NOT account 1101.
        |
        */

        $alternativeArAccountId =
            DB::table('accounts')
                ->insertGetId([
                    'account_group_id' =>
                        $assetGroupId,

                    'code' =>
                        '1197-CR',

                    'name' =>
                        'Customer Receipt AR Test',

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
        | Selected Bank Account
        |--------------------------------------------------------------------------
        */

        $bankAccountId =
            DB::table('accounts')
                ->insertGetId([
                    'account_group_id' =>
                        $assetGroupId,

                    'code' =>
                        '1008-CR',

                    'name' =>
                        'Customer Receipt Bank Test',

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
        | Legacy 1101
        |--------------------------------------------------------------------------
        |
        | Current production code still hard-codes 1101.
        |
        | We intentionally create it so journal posting succeeds.
        | The RED must happen at the mapping assertion, not because
        | account 1101 cannot be found.
        |
        */

        DB::table('accounts')
            ->updateOrInsert(
                [
                    'code' =>
                        '1101',
                ],
                [
                    'account_group_id' =>
                        $assetGroupId,

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
                ]
            );

        /*
        |--------------------------------------------------------------------------
        | Company AR Mapping
        |--------------------------------------------------------------------------
        */

        DB::table('accounting_account_mappings')
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
                        'CUS-CR-001',

                    'name' =>
                        'Customer Receipt Test',

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
        | Sales Invoice
        |--------------------------------------------------------------------------
        |
        | account_receivables requires sales_invoice_id.
        | We only need a posted invoice as the receivable source.
        |
        */

        $salesInvoiceId =
            DB::table('sales_invoices')
                ->insertGetId([
                    'invoice_no' =>
                        'INV-CR-001',

                    'customer_id' =>
                        $customerId,

                    'delivery_order_id' =>
                        999999,

                    'invoice_date' =>
                        '2026-08-10',

                    'due_date' =>
                        '2026-09-10',

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
                        'Customer Receipt source invoice',

                    'created_by' =>
                        $this->data['user_id'],

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        /*
        |--------------------------------------------------------------------------
        | Account Receivable
        |--------------------------------------------------------------------------
        */

        $accountReceivableId =
            DB::table('account_receivables')
                ->insertGetId([
                    'customer_id' =>
                        $customerId,

                    'sales_invoice_id' =>
                        $salesInvoiceId,

                    'invoice_date' =>
                        '2026-08-10',

                    'due_date' =>
                        '2026-09-10',

                    'amount' =>
                        100000,

                    'paid_amount' =>
                        0,

                    'balance_amount' =>
                        100000,

                    'status' =>
                        'OPEN',

                    'remarks' =>
                        'Customer Receipt AR Test',

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        /*
        |--------------------------------------------------------------------------
        | Create Receipt
        |--------------------------------------------------------------------------
        |
        | Receive 40,000:
        |
        | Dr Bank            40,000
        | Cr AR              40,000
        |
        */

        $receipt =
            app(CustomerReceiptService::class)
                ->create(
                    new CustomerReceiptDTO(
                        customerId:
                            $customerId,

                        accountReceivableId:
                            $accountReceivableId,

                        cashBankAccountId:
                            $bankAccountId,

                        amount:
                            40000,

                        remarks:
                            'Customer Receipt mapping test',

                        createdBy:
                            $this->data['user_id'],
                    )
                );

        /*
        |--------------------------------------------------------------------------
        | Receipt
        |--------------------------------------------------------------------------
        */

        $this->assertNotNull(
            $receipt->id
        );

        $this->assertEqualsWithDelta(
            40000,
            (float) $receipt->amount,
            0.01
        );

        $this->assertSame(
            $bankAccountId,
            (int) $receipt->cash_bank_account_id
        );

        /*
        |--------------------------------------------------------------------------
        | Account Receivable Updated
        |--------------------------------------------------------------------------
        */

        $ar =
            DB::table('account_receivables')
                ->where(
                    'id',
                    $accountReceivableId
                )
                ->first();

        $this->assertNotNull(
            $ar
        );

        $this->assertEqualsWithDelta(
            100000,
            (float) $ar->amount,
            0.01
        );

        $this->assertEqualsWithDelta(
            40000,
            (float) $ar->paid_amount,
            0.01
        );

        $this->assertEqualsWithDelta(
            60000,
            (float) $ar->balance_amount,
            0.01
        );

        $this->assertSame(
            'PARTIAL',
            $ar->status
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
                    'CUSTOMER_RECEIPT'
                )
                ->where(
                    'reference_id',
                    $receipt->id
                )
                ->firstOrFail();

        $journal->load(
            'details'
        );

        /*
        |--------------------------------------------------------------------------
        | Selected Bank Debit
        |--------------------------------------------------------------------------
        */

        $bankLine =
            $journal
                ->details
                ->firstWhere(
                    'account_id',
                    $bankAccountId
                );

        $this->assertNotNull(
            $bankLine,
            'Customer Receipt must debit the selected cash/bank account.'
        );

        $this->assertEqualsWithDelta(
            40000,
            (float) $bankLine->debit,
            0.01
        );

        $this->assertEqualsWithDelta(
            0,
            (float) $bankLine->credit,
            0.01
        );

        /*
        |--------------------------------------------------------------------------
        | Company AR Credit
        |--------------------------------------------------------------------------
        */

        $arLine =
            $journal
                ->details
                ->firstWhere(
                    'account_id',
                    $alternativeArAccountId
                );

        $this->assertNotNull(
            $arLine,
            'Customer Receipt must use the company AR account mapping.'
        );

        $this->assertEqualsWithDelta(
            0,
            (float) $arLine->debit,
            0.01
        );

        $this->assertEqualsWithDelta(
            40000,
            (float) $arLine->credit,
            0.01
        );

        /*
        |--------------------------------------------------------------------------
        | Balanced Journal
        |--------------------------------------------------------------------------
        */

        $this->assertEqualsWithDelta(
            40000,
            (float) $journal
                ->details
                ->sum('debit'),
            0.01
        );

        $this->assertEqualsWithDelta(
            40000,
            (float) $journal
                ->details
                ->sum('credit'),
            0.01
        );
    }

    public function test_customer_receipt_rolls_back_when_company_accounting_mapping_is_missing(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Asset Group
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

        /*
        |--------------------------------------------------------------------------
        | Selected Bank Account
        |--------------------------------------------------------------------------
        */

        $bankAccountId =
            DB::table('accounts')
                ->insertGetId([
                    'account_group_id' =>
                        $assetGroupId,

                    'code' =>
                        '1009-CR-RB',

                    'name' =>
                        'Customer Receipt Rollback Bank',

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
        | Customer
        |--------------------------------------------------------------------------
        */

        $customerId =
            DB::table('customers')
                ->insertGetId([
                    'code' =>
                        'CUS-CR-RB',

                    'name' =>
                        'Customer Receipt Rollback',

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
        | Sales Invoice
        |--------------------------------------------------------------------------
        */

        $salesInvoiceId =
            DB::table('sales_invoices')
                ->insertGetId([
                    'invoice_no' =>
                        'INV-CR-RB',

                    'customer_id' =>
                        $customerId,

                    'delivery_order_id' =>
                        999998,

                    'invoice_date' =>
                        '2026-08-11',

                    'due_date' =>
                        '2026-09-11',

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
                        'Customer Receipt rollback source',

                    'created_by' =>
                        $this->data['user_id'],

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        /*
        |--------------------------------------------------------------------------
        | Account Receivable
        |--------------------------------------------------------------------------
        */

        $accountReceivableId =
            DB::table('account_receivables')
                ->insertGetId([
                    'customer_id' =>
                        $customerId,

                    'sales_invoice_id' =>
                        $salesInvoiceId,

                    'invoice_date' =>
                        '2026-08-11',

                    'due_date' =>
                        '2026-09-11',

                    'amount' =>
                        100000,

                    'paid_amount' =>
                        0,

                    'balance_amount' =>
                        100000,

                    'status' =>
                        'OPEN',

                    'remarks' =>
                        'Customer Receipt rollback AR',

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
        | Snapshot Before Transaction
        |--------------------------------------------------------------------------
        */

        $receiptCountBefore =
            DB::table('customer_receipts')
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

            app(CustomerReceiptService::class)
                ->create(
                    new CustomerReceiptDTO(
                        customerId:
                            $customerId,

                        accountReceivableId:
                            $accountReceivableId,

                        cashBankAccountId:
                            $bankAccountId,

                        amount:
                            40000,

                        remarks:
                            'Customer Receipt rollback test',

                        createdBy:
                            $this->data['user_id'],
                    )
                );

            $this->fail(
                'Customer Receipt must fail when company accounting mapping is missing.'
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
        | Receipt Must Roll Back
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $receiptCountBefore,
            DB::table('customer_receipts')
                ->count()
        );

        /*
        |--------------------------------------------------------------------------
        | Journal Must Roll Back
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $journalCountBefore,
            DB::table('journals')
                ->count()
        );

        /*
        |--------------------------------------------------------------------------
        | AR Must Be Restored Completely
        |--------------------------------------------------------------------------
        */

        $ar =
            DB::table('account_receivables')
                ->where(
                    'id',
                    $accountReceivableId
                )
                ->first();

        $this->assertNotNull(
            $ar
        );

        $this->assertEqualsWithDelta(
            100000,
            (float) $ar->amount,
            0.01
        );

        $this->assertEqualsWithDelta(
            0,
            (float) $ar->paid_amount,
            0.01
        );

        $this->assertEqualsWithDelta(
            100000,
            (float) $ar->balance_amount,
            0.01
        );

        $this->assertSame(
            'OPEN',
            $ar->status
        );

        /*
        |--------------------------------------------------------------------------
        | No Receipt For This AR
        |--------------------------------------------------------------------------
        */

        $this->assertFalse(
            DB::table('customer_receipts')
                ->where(
                    'account_receivable_id',
                    $accountReceivableId
                )
                ->exists()
        );

        /*
        |--------------------------------------------------------------------------
        | No Customer Receipt Journal
        |--------------------------------------------------------------------------
        */

        $this->assertFalse(
            DB::table('journals')
                ->where(
                    'reference_type',
                    'CUSTOMER_RECEIPT'
                )
                ->exists()
        );
    }

    public function test_customer_receipt_rejects_zero_or_negative_amount(): void
    {
        $assetGroupId =
            DB::table('account_groups')
                ->where('code', 'AST-T')
                ->value('id');

        $this->assertNotNull(
            $assetGroupId
        );

        $bankAccountId =
            DB::table('accounts')
                ->insertGetId([
                    'account_group_id' =>
                        $assetGroupId,

                    'code' =>
                        '1010-CR-AMT',

                    'name' =>
                        'Customer Receipt Amount Test Bank',

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

        $customerId =
            DB::table('customers')
                ->insertGetId([
                    'code' =>
                        'CUS-CR-AMT',

                    'name' =>
                        'Customer Receipt Amount Test',

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

        $salesInvoiceId =
            DB::table('sales_invoices')
                ->insertGetId([
                    'invoice_no' =>
                        'INV-CR-AMT',

                    'customer_id' =>
                        $customerId,

                    'delivery_order_id' =>
                        999997,

                    'invoice_date' =>
                        '2026-08-12',

                    'due_date' =>
                        '2026-09-12',

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
                        'Customer Receipt amount source',

                    'created_by' =>
                        $this->data['user_id'],

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        $accountReceivableId =
            DB::table('account_receivables')
                ->insertGetId([
                    'customer_id' =>
                        $customerId,

                    'sales_invoice_id' =>
                        $salesInvoiceId,

                    'invoice_date' =>
                        '2026-08-12',

                    'due_date' =>
                        '2026-09-12',

                    'amount' =>
                        100000,

                    'paid_amount' =>
                        0,

                    'balance_amount' =>
                        100000,

                    'status' =>
                        'OPEN',

                    'remarks' =>
                        'Customer Receipt amount AR',

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        $receiptCountBefore =
            DB::table('customer_receipts')
                ->count();

        foreach ([0, -1000] as $invalidAmount) {

            try {

                app(CustomerReceiptService::class)
                    ->create(
                        new CustomerReceiptDTO(
                            customerId:
                                $customerId,

                            accountReceivableId:
                                $accountReceivableId,

                            cashBankAccountId:
                                $bankAccountId,

                            amount:
                                (float) $invalidAmount,

                            remarks:
                                'Invalid amount test',

                            createdBy:
                                $this->data['user_id'],
                        )
                    );

                $this->fail(
                    'Customer Receipt must reject zero or negative amount.'
                );

            } catch (\RuntimeException $e) {

                $this->assertSame(
                    'Receipt amount must be greater than zero.',
                    $e->getMessage()
                );
            }
        }

        $this->assertSame(
            $receiptCountBefore,
            DB::table('customer_receipts')
                ->count()
        );

        $ar =
            DB::table('account_receivables')
                ->where('id', $accountReceivableId)
                ->first();

        $this->assertEqualsWithDelta(
            0,
            (float) $ar->paid_amount,
            0.01
        );

        $this->assertEqualsWithDelta(
            100000,
            (float) $ar->balance_amount,
            0.01
        );

        $this->assertSame(
            'OPEN',
            $ar->status
        );
    }

    public function test_customer_receipt_rejects_customer_mismatch_with_receivable(): void
    {
        $assetGroupId =
            DB::table('account_groups')
                ->where('code', 'AST-T')
                ->value('id');

        $this->assertNotNull(
            $assetGroupId
        );

        $bankAccountId =
            DB::table('accounts')
                ->insertGetId([
                    'account_group_id' =>
                        $assetGroupId,

                    'code' =>
                        '1011-CR-CUST',

                    'name' =>
                        'Customer Receipt Customer Test Bank',

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

        $customerAId =
            DB::table('customers')
                ->insertGetId([
                    'code' =>
                        'CUS-CR-A',

                    'name' =>
                        'Customer A',

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

        $customerBId =
            DB::table('customers')
                ->insertGetId([
                    'code' =>
                        'CUS-CR-B',

                    'name' =>
                        'Customer B',

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

        $salesInvoiceId =
            DB::table('sales_invoices')
                ->insertGetId([
                    'invoice_no' =>
                        'INV-CR-CUST',

                    'customer_id' =>
                        $customerAId,

                    'delivery_order_id' =>
                        999996,

                    'invoice_date' =>
                        '2026-08-13',

                    'due_date' =>
                        '2026-09-13',

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
                        'Customer mismatch source',

                    'created_by' =>
                        $this->data['user_id'],

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        $accountReceivableId =
            DB::table('account_receivables')
                ->insertGetId([
                    'customer_id' =>
                        $customerAId,

                    'sales_invoice_id' =>
                        $salesInvoiceId,

                    'invoice_date' =>
                        '2026-08-13',

                    'due_date' =>
                        '2026-09-13',

                    'amount' =>
                        100000,

                    'paid_amount' =>
                        0,

                    'balance_amount' =>
                        100000,

                    'status' =>
                        'OPEN',

                    'remarks' =>
                        'Customer mismatch AR',

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        $receiptCountBefore =
            DB::table('customer_receipts')
                ->count();

        try {

            app(CustomerReceiptService::class)
                ->create(
                    new CustomerReceiptDTO(
                        customerId:
                            $customerBId,

                        accountReceivableId:
                            $accountReceivableId,

                        cashBankAccountId:
                            $bankAccountId,

                        amount:
                            40000,

                        remarks:
                            'Customer mismatch test',

                        createdBy:
                            $this->data['user_id'],
                    )
                );

            $this->fail(
                'Customer Receipt must reject customer mismatch.'
            );

        } catch (\RuntimeException $e) {

            $this->assertSame(
                'Customer does not match account receivable.',
                $e->getMessage()
            );
        }

        $this->assertSame(
            $receiptCountBefore,
            DB::table('customer_receipts')
                ->count()
        );

        $ar =
            DB::table('account_receivables')
                ->where('id', $accountReceivableId)
                ->first();

        $this->assertEqualsWithDelta(
            0,
            (float) $ar->paid_amount,
            0.01
        );

        $this->assertEqualsWithDelta(
            100000,
            (float) $ar->balance_amount,
            0.01
        );

        $this->assertSame(
            'OPEN',
            $ar->status
        );
    }

    public function test_customer_receipt_rejects_payment_exceeding_receivable_balance(): void
    {
        $assetGroupId =
            DB::table('account_groups')
                ->where('code', 'AST-T')
                ->value('id');

        $this->assertNotNull(
            $assetGroupId
        );

        $bankAccountId =
            DB::table('accounts')
                ->insertGetId([
                    'account_group_id' =>
                        $assetGroupId,
                    'code' =>
                        '1012-CR-OVER',
                    'name' =>
                        'Customer Receipt Overpayment Bank',
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

        $customerId =
            DB::table('customers')
                ->insertGetId([
                    'code' =>
                        'CUS-CR-OVER',
                    'name' =>
                        'Customer Receipt Overpayment',
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

        $salesInvoiceId =
            DB::table('sales_invoices')
                ->insertGetId([
                    'invoice_no' =>
                        'INV-CR-OVER',
                    'customer_id' =>
                        $customerId,
                    'delivery_order_id' =>
                        999995,
                    'invoice_date' =>
                        '2026-08-14',
                    'due_date' =>
                        '2026-09-14',
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
                        'Overpayment source invoice',
                    'created_by' =>
                        $this->data['user_id'],
                    'created_at' =>
                        now(),
                    'updated_at' =>
                        now(),
                ]);

        $accountReceivableId =
            DB::table('account_receivables')
                ->insertGetId([
                    'customer_id' =>
                        $customerId,
                    'sales_invoice_id' =>
                        $salesInvoiceId,
                    'invoice_date' =>
                        '2026-08-14',
                    'due_date' =>
                        '2026-09-14',
                    'amount' =>
                        100000,
                    'paid_amount' =>
                        60000,
                    'balance_amount' =>
                        40000,
                    'status' =>
                        'PARTIAL',
                    'remarks' =>
                        'Overpayment AR',
                    'created_at' =>
                        now(),
                    'updated_at' =>
                        now(),
                ]);

        $receiptCountBefore =
            DB::table('customer_receipts')
                ->count();

        $journalCountBefore =
            DB::table('journals')
                ->count();

        try {

            app(CustomerReceiptService::class)
                ->create(
                    new CustomerReceiptDTO(
                        customerId:
                            $customerId,
                        accountReceivableId:
                            $accountReceivableId,
                        cashBankAccountId:
                            $bankAccountId,
                        amount:
                            50000,
                        remarks:
                            'Overpayment test',
                        createdBy:
                            $this->data['user_id'],
                    )
                );

            $this->fail(
                'Customer Receipt must reject payment exceeding receivable balance.'
            );

        } catch (\RuntimeException $e) {

            $this->assertSame(
                'Payment exceeds receivable balance',
                $e->getMessage()
            );
        }

        $this->assertSame(
            $receiptCountBefore,
            DB::table('customer_receipts')->count()
        );

        $this->assertSame(
            $journalCountBefore,
            DB::table('journals')->count()
        );

        $ar =
            DB::table('account_receivables')
                ->where('id', $accountReceivableId)
                ->first();

        $this->assertEqualsWithDelta(
            60000,
            (float) $ar->paid_amount,
            0.01
        );

        $this->assertEqualsWithDelta(
            40000,
            (float) $ar->balance_amount,
            0.01
        );

        $this->assertSame(
            'PARTIAL',
            $ar->status
        );

        $this->assertFalse(
            DB::table('customer_receipts')
                ->where(
                    'account_receivable_id',
                    $accountReceivableId
                )
                ->exists()
        );
    }

    public function test_customer_receipt_rejects_already_paid_receivable(): void
    {
        $assetGroupId =
            DB::table('account_groups')
                ->where('code', 'AST-T')
                ->value('id');

        $this->assertNotNull(
            $assetGroupId
        );

        $bankAccountId =
            DB::table('accounts')
                ->insertGetId([
                    'account_group_id' =>
                        $assetGroupId,
                    'code' =>
                        '1013-CR-PAID',
                    'name' =>
                        'Customer Receipt Paid AR Bank',
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

        $customerId =
            DB::table('customers')
                ->insertGetId([
                    'code' =>
                        'CUS-CR-PAID',
                    'name' =>
                        'Customer Receipt Paid AR',
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

        $salesInvoiceId =
            DB::table('sales_invoices')
                ->insertGetId([
                    'invoice_no' =>
                        'INV-CR-PAID',
                    'customer_id' =>
                        $customerId,
                    'delivery_order_id' =>
                        999994,
                    'invoice_date' =>
                        '2026-08-15',
                    'due_date' =>
                        '2026-09-15',
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
                        'Paid AR source invoice',
                    'created_by' =>
                        $this->data['user_id'],
                    'created_at' =>
                        now(),
                    'updated_at' =>
                        now(),
                ]);

        $accountReceivableId =
            DB::table('account_receivables')
                ->insertGetId([
                    'customer_id' =>
                        $customerId,
                    'sales_invoice_id' =>
                        $salesInvoiceId,
                    'invoice_date' =>
                        '2026-08-15',
                    'due_date' =>
                        '2026-09-15',
                    'amount' =>
                        100000,
                    'paid_amount' =>
                        100000,
                    'balance_amount' =>
                        0,
                    'status' =>
                        'PAID',
                    'remarks' =>
                        'Already paid AR',
                    'created_at' =>
                        now(),
                    'updated_at' =>
                        now(),
                ]);

        $receiptCountBefore =
            DB::table('customer_receipts')
                ->count();

        $journalCountBefore =
            DB::table('journals')
                ->count();

        try {

            app(CustomerReceiptService::class)
                ->create(
                    new CustomerReceiptDTO(
                        customerId:
                            $customerId,
                        accountReceivableId:
                            $accountReceivableId,
                        cashBankAccountId:
                            $bankAccountId,
                        amount:
                            1000,
                        remarks:
                            'Already paid AR test',
                        createdBy:
                            $this->data['user_id'],
                    )
                );

            $this->fail(
                'Customer Receipt must reject an already paid receivable.'
            );

        } catch (\RuntimeException $e) {

            $this->assertSame(
                'Account receivable is already paid.',
                $e->getMessage()
            );
        }

        $this->assertSame(
            $receiptCountBefore,
            DB::table('customer_receipts')->count()
        );

        $this->assertSame(
            $journalCountBefore,
            DB::table('journals')->count()
        );

        $ar =
            DB::table('account_receivables')
                ->where('id', $accountReceivableId)
                ->first();

        $this->assertEqualsWithDelta(
            100000,
            (float) $ar->paid_amount,
            0.01
        );

        $this->assertEqualsWithDelta(
            0,
            (float) $ar->balance_amount,
            0.01
        );

        $this->assertSame(
            'PAID',
            $ar->status
        );

        $this->assertFalse(
            DB::table('customer_receipts')
                ->where(
                    'account_receivable_id',
                    $accountReceivableId
                )
                ->exists()
        );
    }
}
