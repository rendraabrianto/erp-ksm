<?php

namespace Tests\Feature;

use App\DTO\APAgingFilterDTO;
use App\DTO\ARAgingFilterDTO;
use App\DTO\BalanceSheetFilterDTO;
use App\DTO\CashFlowFilterDTO;
use App\DTO\ProfitLossFilterDTO;
use App\DTO\TrialBalanceFilterDTO;
use App\Services\APAgingService;
use App\Services\ARAgingService;
use App\Services\BalanceSheetService;
use App\Services\CashFlowService;
use App\Services\ProfitLossService;
use App\Services\TrialBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;

class AccountingReportCompanyIsolationTest extends TestCase
{
    use RefreshDatabase;

    private array $companyA;

    private array $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        $base =
            InventoryReconciliationTestData::create();

        $this->companyA =
            $this->prepareCompanyA($base);

        $this->companyB =
            $this->createCompanyB();
    }

    public function test_profit_loss_is_isolated_by_company(): void
    {
        $service =
            app(ProfitLossService::class);

        $reportA =
            $service->getReport(
                $this->companyA['company_id'],
                new ProfitLossFilterDTO(
                    dateFrom: '2026-09-01',
                    dateTo: '2026-09-30',
                )
            );

        $reportB =
            $service->getReport(
                $this->companyB['company_id'],
                new ProfitLossFilterDTO(
                    dateFrom: '2026-09-01',
                    dateTo: '2026-09-30',
                )
            );

        $this->assertEquals(
            1000.0,
            (float) $reportA['revenue']
        );

        $this->assertEquals(
            200.0,
            (float) $reportA['cogs']
        );

        $this->assertEquals(
            100.0,
            (float) $reportA['expense']
        );

        $this->assertEquals(
            700.0,
            (float) $reportA['net_profit']
        );

        $this->assertEquals(
            5000.0,
            (float) $reportB['revenue']
        );

        $this->assertEquals(
            1000.0,
            (float) $reportB['cogs']
        );

        $this->assertEquals(
            500.0,
            (float) $reportB['expense']
        );

        $this->assertEquals(
            3500.0,
            (float) $reportB['net_profit']
        );
    }

    public function test_balance_sheet_is_isolated_by_company(): void
    {
        $service =
            app(BalanceSheetService::class);

        /*
        * --------------------------------------------------------------
        * Baseline Company A
        * --------------------------------------------------------------
        *
        * Company A berasal dari historical inventory fixture sehingga
        * memiliki saldo sebelum September.
        *
        * Kita tidak boleh mengasumsikan saldo awal = 0.
        */
        $reportABeforeForeignInjection =
            $service->getReport(
                $this->companyA['company_id'],
                new BalanceSheetFilterDTO(
                    dateFrom: '2026-09-01',
                    dateTo: '2026-09-30',
                )
            );

        /*
        * --------------------------------------------------------------
        * Baseline Company B
        * --------------------------------------------------------------
        */
        $reportBBeforeForeignInjection =
            $service->getReport(
                $this->companyB['company_id'],
                new BalanceSheetFilterDTO(
                    dateFrom: '2026-09-01',
                    dateTo: '2026-09-30',
                )
            );

        /*
        * --------------------------------------------------------------
        * Inject large accounting journal into Company B
        * --------------------------------------------------------------
        *
        * Kalau repository Company A bocor ke Company B, angka Company A
        * akan berubah setelah journal ini dibuat.
        */
        $this->createAccountingJournal(
            companyId: $this->companyB['company_id'],
            userId: $this->companyB['user_id'],
            journalNo: 'ISO-B-LEAK-CHECK',
            referenceType: 'CUSTOMER_RECEIPT',
            referenceId: 99002,
            cashAccountId: $this->companyB['cash_account_id'],
            revenueAccountId: $this->companyB['revenue_account_id'],
            cogsAccountId: $this->companyB['cogs_account_id'],
            expenseAccountId: $this->companyB['expense_account_id'],
            liabilityAccountId: $this->companyB['ap_account_id'],
            cashAmount: 900000,
            cogsAmount: 200000,
            expenseAmount: 100000,
        );

        /*
        * --------------------------------------------------------------
        * Re-read Company A
        * --------------------------------------------------------------
        */
        $reportAAfterForeignInjection =
            $service->getReport(
                $this->companyA['company_id'],
                new BalanceSheetFilterDTO(
                    dateFrom: '2026-09-01',
                    dateTo: '2026-09-30',
                )
            );

        /*
        * --------------------------------------------------------------
        * Re-read Company B
        * --------------------------------------------------------------
        */
        $reportBAfterForeignInjection =
            $service->getReport(
                $this->companyB['company_id'],
                new BalanceSheetFilterDTO(
                    dateFrom: '2026-09-01',
                    dateTo: '2026-09-30',
                )
            );

        /*
        * --------------------------------------------------------------
        * Company A MUST NOT change
        * --------------------------------------------------------------
        */
        $this->assertEquals(
            (float) $reportABeforeForeignInjection['total_assets'],
            (float) $reportAAfterForeignInjection['total_assets']
        );

        $this->assertEquals(
            (float) $reportABeforeForeignInjection['total_liabilities'],
            (float) $reportAAfterForeignInjection['total_liabilities']
        );

        $this->assertEquals(
            (float) $reportABeforeForeignInjection['total_equity'],
            (float) $reportAAfterForeignInjection['total_equity']
        );

        /*
        * --------------------------------------------------------------
        * Company B MUST change
        * --------------------------------------------------------------
        */
        $this->assertNotEquals(
            (float) $reportBBeforeForeignInjection['total_assets'],
            (float) $reportBAfterForeignInjection['total_assets']
        );

        $this->assertNotEquals(
            (float) $reportBBeforeForeignInjection['total_liabilities'],
            (float) $reportBAfterForeignInjection['total_liabilities']
        );

        $this->assertNotEquals(
            (float) $reportBBeforeForeignInjection['total_equity'],
            (float) $reportBAfterForeignInjection['total_equity']
        );

        /*
        * --------------------------------------------------------------
        * Strong isolation check
        * --------------------------------------------------------------
        *
        * Account Company B tidak boleh muncul pada rows Company A.
        */
        $assetAccountIdsA =
            collect(
                $reportAAfterForeignInjection['assets']
            )->pluck('account_id');

        $liabilityAccountIdsA =
            collect(
                $reportAAfterForeignInjection['liabilities']
            )->pluck('account_id');

        $this->assertFalse(
            $assetAccountIdsA->contains(
                $this->companyB['cash_account_id']
            )
        );

        $this->assertFalse(
            $liabilityAccountIdsA->contains(
                $this->companyB['ap_account_id']
            )
        );
    }

    public function test_trial_balance_is_isolated_by_company(): void
    {
        $service =
            app(TrialBalanceService::class);

        $reportA =
            $service->getReport(
                $this->companyA['company_id'],
                new TrialBalanceFilterDTO(
                    dateFrom: '2026-09-01',
                    dateTo: '2026-09-30',
                )
            );

        $reportB =
            $service->getReport(
                $this->companyB['company_id'],
                new TrialBalanceFilterDTO(
                    dateFrom: '2026-09-01',
                    dateTo: '2026-09-30',
                )
            );

        $accountIdsA =
            collect($reportA)
                ->pluck('account_id');

        $accountIdsB =
            collect($reportB)
                ->pluck('account_id');

        $this->assertTrue(
            $accountIdsA->contains(
                $this->companyA['cash_account_id']
            )
        );

        $this->assertFalse(
            $accountIdsA->contains(
                $this->companyB['cash_account_id']
            )
        );

        $this->assertTrue(
            $accountIdsB->contains(
                $this->companyB['cash_account_id']
            )
        );

        $this->assertFalse(
            $accountIdsB->contains(
                $this->companyA['cash_account_id']
            )
        );
    }

    public function test_cash_flow_is_isolated_by_company(): void
    {
        $service =
            app(CashFlowService::class);

        $reportA =
            $service->getReport(
                $this->companyA['company_id'],
                new CashFlowFilterDTO(
                    dateFrom: '2026-09-01',
                    dateTo: '2026-09-30',
                )
            );

        $reportB =
            $service->getReport(
                $this->companyB['company_id'],
                new CashFlowFilterDTO(
                    dateFrom: '2026-09-01',
                    dateTo: '2026-09-30',
                )
            );

        $this->assertEquals(
            1000.0,
            (float) $reportA['total_operating']
        );

        $this->assertEquals(
            5000.0,
            (float) $reportB['total_operating']
        );

        $this->assertCount(
            1,
            $reportA['operating']
        );

        $this->assertCount(
            1,
            $reportB['operating']
        );
    }

    public function test_ar_aging_is_isolated_by_company(): void
    {
        $service =
            app(ARAgingService::class);

        $reportA =
            $service->getReport(
                $this->companyA['company_id'],
                new ARAgingFilterDTO(
                    asOfDate: '2026-09-30'
                )
            );

        $reportB =
            $service->getReport(
                $this->companyB['company_id'],
                new ARAgingFilterDTO(
                    asOfDate: '2026-09-30'
                )
            );

        $this->assertEquals(
            1200.0,
            (float) $reportA['total_outstanding']
        );

        $this->assertEquals(
            6200.0,
            (float) $reportB['total_outstanding']
        );

        $rowsA =
            collect([
                ...$reportA['current'],
                ...$reportA['days_1_30'],
                ...$reportA['days_31_60'],
                ...$reportA['days_61_90'],
                ...$reportA['over_90'],
            ]);

        $rowsB =
            collect([
                ...$reportB['current'],
                ...$reportB['days_1_30'],
                ...$reportB['days_31_60'],
                ...$reportB['days_61_90'],
                ...$reportB['over_90'],
            ]);

        $this->assertTrue(
            $rowsA
                ->pluck('customer_id')
                ->contains(
                    $this->companyA['customer_id']
                )
        );

        $this->assertFalse(
            $rowsA
                ->pluck('customer_id')
                ->contains(
                    $this->companyB['customer_id']
                )
        );

        $this->assertTrue(
            $rowsB
                ->pluck('customer_id')
                ->contains(
                    $this->companyB['customer_id']
                )
        );
    }

    public function test_ap_aging_is_isolated_by_company(): void
    {
        $service =
            app(APAgingService::class);

        $reportA =
            $service->getReport(
                $this->companyA['company_id'],
                new APAgingFilterDTO(
                    asOfDate: '2026-09-30'
                )
            );

        $reportB =
            $service->getReport(
                $this->companyB['company_id'],
                new APAgingFilterDTO(
                    asOfDate: '2026-09-30'
                )
            );

        $this->assertEquals(
            1300.0,
            (float) $reportA['total_outstanding']
        );

        $this->assertEquals(
            6300.0,
            (float) $reportB['total_outstanding']
        );

        $rowsA =
            collect([
                ...$reportA['current'],
                ...$reportA['days_1_30'],
                ...$reportA['days_31_60'],
                ...$reportA['days_61_90'],
                ...$reportA['over_90'],
            ]);

        $rowsB =
            collect([
                ...$reportB['current'],
                ...$reportB['days_1_30'],
                ...$reportB['days_31_60'],
                ...$reportB['days_61_90'],
                ...$reportB['over_90'],
            ]);

        $this->assertTrue(
            $rowsA
                ->pluck('supplier_name')
                ->contains('SUPPLIER-A')
        );

        $this->assertFalse(
            $rowsA
                ->pluck('supplier_name')
                ->contains('SUPPLIER-B')
        );

        $this->assertTrue(
            $rowsB
                ->pluck('supplier_name')
                ->contains('SUPPLIER-B')
        );
    }

    private function prepareCompanyA(
        array $base
    ): array {
        $revenueGroupId =
            $this->createAccountGroup(
                $base['company_id'],
                'REV-A',
                'Revenue A'
            );

        $revenueAccountId =
            $this->createAccount(
                $base['company_id'],
                $revenueGroupId,
                '4001-A',
                'Sales A',
                'CREDIT'
            );

        $cashAccountId =
            $this->createAccount(
                $base['company_id'],
                $this->accountGroupId(
                    $base['inventory_account_id']
                ),
                '1001-A',
                'Cash A',
                'DEBIT'
            );

        $expenseAccountId =
            $this->createAccount(
                $base['company_id'],
                $this->accountGroupId(
                    $base['cogs_account_id']
                ),
                '6001-A',
                'Operating Expense A',
                'DEBIT'
            );

        $this->createAccountingJournal(
            companyId: $base['company_id'],
            userId: $base['user_id'],
            journalNo: 'ISO-A-001',
            referenceType: 'CUSTOMER_RECEIPT',
            referenceId: 9001,
            cashAccountId: $cashAccountId,
            revenueAccountId: $revenueAccountId,
            cogsAccountId: $base['cogs_account_id'],
            expenseAccountId: $expenseAccountId,
            liabilityAccountId: $base['ap_account_id'],
            cashAmount: 1000,
            cogsAmount: 200,
            expenseAmount: 100,
        );

        $salesInvoiceId =
            $this->createSalesInvoice(
                companyId: $base['company_id'],
                customerId: $base['customer_id'],
                userId: $base['user_id'],
                invoiceNo: 'ISO-SI-A-001',
                amount: 1200,
            );

        DB::table('account_receivables')->insert([
            'company_id' => $base['company_id'],
            'customer_id' => $base['customer_id'],
            'sales_invoice_id' => $salesInvoiceId,
            'invoice_date' => '2026-09-01',
            'due_date' => '2026-09-15',
            'amount' => 1200,
            'paid_amount' => 0,
            'balance_amount' => 1200,
            'status' => 'OPEN',
            'remarks' => 'AR Company A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('account_payables')->insert([
            'company_id' => $base['company_id'],
            'reference_type' => 'TEST',
            'reference_id' => 9101,
            'supplier_name' => 'SUPPLIER-A',
            'invoice_date' => '2026-09-01',
            'due_date' => '2026-09-15',
            'amount' => 1300,
            'paid_amount' => 0,
            'balance_amount' => 1300,
            'status' => 'OPEN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            ...$base,
            'cash_account_id' => $cashAccountId,
            'revenue_account_id' => $revenueAccountId,
            'expense_account_id' => $expenseAccountId,
        ];
    }

    private function createCompanyB(): array
    {
        $companyId =
            DB::table('companies')->insertGetId([
                'code' => 'ISO-B',
                'name' => 'Isolation Company B',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $branchId =
            DB::table('branches')->insertGetId([
                'company_id' => $companyId,
                'code' => 'BR-B',
                'name' => 'Branch B',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $userId =
            DB::table('users')->insertGetId([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'name' => 'User B',
                'email' => 'accounting-isolation-b@example.test',
                'password' => Hash::make('password'),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $assetGroupId =
            $this->createAccountGroup(
                $companyId,
                'AST-B',
                'Asset B'
            );

        $liabilityGroupId =
            $this->createAccountGroup(
                $companyId,
                'LIA-B',
                'Liability B'
            );

        $expenseGroupId =
            $this->createAccountGroup(
                $companyId,
                'EXP-B',
                'Expense B'
            );

        $revenueGroupId =
            $this->createAccountGroup(
                $companyId,
                'REV-B',
                'Revenue B'
            );

        $cashAccountId =
            $this->createAccount(
                $companyId,
                $assetGroupId,
                '1001-B',
                'Cash B',
                'DEBIT'
            );

        $arAccountId =
            $this->createAccount(
                $companyId,
                $assetGroupId,
                '1101-B',
                'AR B',
                'DEBIT'
            );

        $apAccountId =
            $this->createAccount(
                $companyId,
                $liabilityGroupId,
                '2001-B',
                'AP B',
                'CREDIT'
            );

        $cogsAccountId =
            $this->createAccount(
                $companyId,
                $expenseGroupId,
                '5001-B',
                'COGS B',
                'DEBIT'
            );

        $expenseAccountId =
            $this->createAccount(
                $companyId,
                $expenseGroupId,
                '6001-B',
                'Operating Expense B',
                'DEBIT'
            );

        $revenueAccountId =
            $this->createAccount(
                $companyId,
                $revenueGroupId,
                '4001-B',
                'Sales B',
                'CREDIT'
            );

        $customerId =
            DB::table('customers')->insertGetId([
                'company_id' => $companyId,
                'code' => 'CUSTOMER-B',
                'name' => 'Customer B',
                'phone' => null,
                'email' => null,
                'address' => null,
                'credit_limit' => 0,
                'credit_days' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $this->createAccountingJournal(
            companyId: $companyId,
            userId: $userId,
            journalNo: 'ISO-B-001',
            referenceType: 'CUSTOMER_RECEIPT',
            referenceId: 9002,
            cashAccountId: $cashAccountId,
            revenueAccountId: $revenueAccountId,
            cogsAccountId: $cogsAccountId,
            expenseAccountId: $expenseAccountId,
            liabilityAccountId: $apAccountId,
            cashAmount: 5000,
            cogsAmount: 1000,
            expenseAmount: 500,
        );

        $salesInvoiceId =
            $this->createSalesInvoice(
                companyId: $companyId,
                customerId: $customerId,
                userId: $userId,
                invoiceNo: 'ISO-SI-B-001',
                amount: 6200,
            );

        DB::table('account_receivables')->insert([
            'company_id' => $companyId,
            'customer_id' => $customerId,
            'sales_invoice_id' => $salesInvoiceId,
            'invoice_date' => '2026-09-01',
            'due_date' => '2026-09-15',
            'amount' => 6200,
            'paid_amount' => 0,
            'balance_amount' => 6200,
            'status' => 'OPEN',
            'remarks' => 'AR Company B',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('account_payables')->insert([
            'company_id' => $companyId,
            'reference_type' => 'TEST',
            'reference_id' => 9102,
            'supplier_name' => 'SUPPLIER-B',
            'invoice_date' => '2026-09-01',
            'due_date' => '2026-09-15',
            'amount' => 6300,
            'paid_amount' => 0,
            'balance_amount' => 6300,
            'status' => 'OPEN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'user_id' => $userId,
            'customer_id' => $customerId,
            'cash_account_id' => $cashAccountId,
            'ar_account_id' => $arAccountId,
            'ap_account_id' => $apAccountId,
            'cogs_account_id' => $cogsAccountId,
            'expense_account_id' => $expenseAccountId,
            'revenue_account_id' => $revenueAccountId,
            'sales_invoice_id' => $salesInvoiceId,
        ];
    }

    private function createAccountGroup(
        int $companyId,
        string $code,
        string $name
    ): int {
        return DB::table('account_groups')
            ->insertGetId([
                'company_id' => $companyId,
                'code' => $code,
                'name' => $name,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function createAccount(
        int $companyId,
        int $accountGroupId,
        string $code,
        string $name,
        string $normalBalance
    ): int {
        return DB::table('accounts')
            ->insertGetId([
                'company_id' => $companyId,
                'account_group_id' => $accountGroupId,
                'code' => $code,
                'name' => $name,
                'normal_balance' => $normalBalance,
                'is_header' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function accountGroupId(
    int $accountId
    ): int {
        return (int) DB::table('accounts')
            ->where('id', $accountId)
            ->value('account_group_id');
    }

    private function createSalesInvoice(
        int $companyId,
        int $customerId,
        int $userId,
        string $invoiceNo,
        float $amount
    ): int {
        /*
        * --------------------------------------------------------------
        * Sales Order
        * --------------------------------------------------------------
        */
        $salesOrderId =
            DB::table('sales_orders')
                ->insertGetId([
                    'company_id' => $companyId,
                    'so_no' => 'SO-' . $invoiceNo,
                    'customer_id' => $customerId,
                    'order_date' => '2026-09-01',
                    'delivery_date' => '2026-09-05',
                    'status' => 'COMPLETED',
                    'remarks' => 'Accounting Company Isolation Test',
                    'created_by' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

        /*
        * --------------------------------------------------------------
        * Delivery Order
        * --------------------------------------------------------------
        */
        $deliveryOrderId =
            DB::table('delivery_orders')
                ->insertGetId([
                    'company_id' => $companyId,
                    'do_no' => 'DO-' . $invoiceNo,
                    'sales_order_id' => $salesOrderId,
                    'delivery_date' => '2026-09-05',
                    'status' => 'POSTED',
                    'remarks' => 'Accounting Company Isolation Test',
                    'created_by' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

        /*
        * --------------------------------------------------------------
        * Sales Invoice
        * --------------------------------------------------------------
        */
        return DB::table('sales_invoices')
            ->insertGetId([
                'company_id' => $companyId,
                'invoice_no' => $invoiceNo,
                'customer_id' => $customerId,
                'delivery_order_id' => $deliveryOrderId,
                'invoice_date' => '2026-09-01',
                'due_date' => '2026-09-15',
                'subtotal' => $amount,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'grand_total' => $amount,
                'status' => 'POSTED',
                'remarks' => 'Accounting Company Isolation Test',
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function createAccountingJournal(
        int $companyId,
        int $userId,
        string $journalNo,
        string $referenceType,
        int $referenceId,
        int $cashAccountId,
        int $revenueAccountId,
        int $cogsAccountId,
        int $expenseAccountId,
        int $liabilityAccountId,
        float $cashAmount,
        float $cogsAmount,
        float $expenseAmount
    ): int {
        $journalId =
            DB::table('journals')
                ->insertGetId([
                    'company_id' => $companyId,
                    'journal_date' => '2026-09-10',
                    'journal_no' => $journalNo,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'description' => 'Accounting Company Isolation Test',
                    'created_by' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

        /*
        * Cash / Bank
        */
        DB::table('journal_details')->insert([
            'journal_id' => $journalId,
            'account_id' => $cashAccountId,
            'debit' => $cashAmount,
            'credit' => 0,
            'description' => 'Cash receipt',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
        * Revenue
        */
        DB::table('journal_details')->insert([
            'journal_id' => $journalId,
            'account_id' => $revenueAccountId,
            'debit' => 0,
            'credit' => $cashAmount,
            'description' => 'Revenue',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
        * Cost of Goods Sold
        */
        DB::table('journal_details')->insert([
            'journal_id' => $journalId,
            'account_id' => $cogsAccountId,
            'debit' => $cogsAmount,
            'credit' => 0,
            'description' => 'Cost of Goods Sold',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
        * Operating Expense
        */
        DB::table('journal_details')->insert([
            'journal_id' => $journalId,
            'account_id' => $expenseAccountId,
            'debit' => $expenseAmount,
            'credit' => 0,
            'description' => 'Operating Expense',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
        * Balancing liability.
        */
        DB::table('journal_details')->insert([
            'journal_id' => $journalId,
            'account_id' => $liabilityAccountId,
            'debit' => 0,
            'credit' => $cogsAmount + $expenseAmount,
            'description' => 'Balancing Liability',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $journalId;
    }
}