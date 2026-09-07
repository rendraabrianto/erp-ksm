<?php

namespace Tests\Feature;

use App\DTO\PaymentVoucherDTO;
use App\Models\Journal;
use App\Services\PaymentVoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\InventoryReconciliationTestData;
use Tests\TestCase;

class PaymentVoucherClosedLoopTest extends TestCase
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
        | Payment Voucher Document Sequence
        |--------------------------------------------------------------------------
        */

        DB::table('document_sequences')
            ->updateOrInsert(
                [
                    'document_type' =>
                        'PV',
                ],
                [
                    'prefix' =>
                        'PV',

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

    public function test_payment_voucher_uses_company_ap_mapping_and_selected_cash_bank_account(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Liability Account Group
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
        | Asset Account Group
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

        /*
        |--------------------------------------------------------------------------
        | Alternative AP Account
        |--------------------------------------------------------------------------
        */

        $alternativeApAccountId =
            DB::table('accounts')
                ->insertGetId([
                    'account_group_id' =>
                        $liabilityGroupId,

                    'code' =>
                        '2997-PV',

                    'name' =>
                        'Alternative AP Payment Voucher',

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
        | Selected Cash / Bank Account
        |--------------------------------------------------------------------------
        */

        $cashBankAccountId =
            DB::table('accounts')
                ->insertGetId([
                    'account_group_id' =>
                        $assetGroupId,

                    'code' =>
                        '1009-PV',

                    'name' =>
                        'Bank Payment Voucher Test',

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
        | Legacy AP
        |--------------------------------------------------------------------------
        |
        | Current AutoJournalService masih menggunakan account code 2001.
        | Kita pastikan account tersebut ada agar RED terjadi pada assertion
        | dynamic mapping, bukan karena AccountNotFound.
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
        | Change Company AP Mapping
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
                'ap_account_id' =>
                    $alternativeApAccountId,

                'updated_at' =>
                    now(),
            ]);

        /*
        |--------------------------------------------------------------------------
        | Account Payable
        |--------------------------------------------------------------------------
        */

        $accountPayableId =
            DB::table('account_payables')
                ->insertGetId([
                    'reference_type' =>
                        'PURCHASE_INVOICE',

                    'reference_id' =>
                        999001,

                    'supplier_name' =>
                        'Supplier Payment Voucher Test',

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

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        /*
        |--------------------------------------------------------------------------
        | Create Payment Voucher
        |--------------------------------------------------------------------------
        */

        $voucher =
            app(PaymentVoucherService::class)
                ->create(
                    new PaymentVoucherDTO(
                        accountPayableId:
                            $accountPayableId,

                        cashBankAccountId:
                            $cashBankAccountId,

                        amount:
                            40000,

                        paymentMethod:
                            'BANK_TRANSFER',

                        remarks:
                            'Dynamic AP mapping test',

                        createdBy:
                            $this->data['user_id'],
                    )
                );

        /*
        |--------------------------------------------------------------------------
        | Voucher
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $accountPayableId,
            (int) $voucher->account_payable_id
        );

        $this->assertSame(
            $cashBankAccountId,
            (int) $voucher->cash_bank_account_id
        );

        $this->assertEqualsWithDelta(
            40000,
            (float) $voucher->amount,
            0.01
        );

        /*
        |--------------------------------------------------------------------------
        | AP Updated
        |--------------------------------------------------------------------------
        */

        $ap =
            DB::table('account_payables')
                ->where(
                    'id',
                    $accountPayableId
                )
                ->first();

        $this->assertNotNull(
            $ap
        );

        $this->assertEqualsWithDelta(
            40000,
            (float) $ap->paid_amount,
            0.01
        );

        $this->assertEqualsWithDelta(
            60000,
            (float) $ap->balance_amount,
            0.01
        );

        $this->assertSame(
            'PARTIAL',
            $ap->status
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
                    'PAYMENT_VOUCHER'
                )
                ->where(
                    'reference_id',
                    $voucher->id
                )
                ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Dynamic AP — Debit
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
            'Payment Voucher must use the company AP account mapping.'
        );

        $this->assertEqualsWithDelta(
            40000,
            (float) $apLine->debit,
            0.01
        );

        $this->assertEqualsWithDelta(
            0,
            (float) $apLine->credit,
            0.01
        );

        /*
        |--------------------------------------------------------------------------
        | Selected Cash / Bank — Credit
        |--------------------------------------------------------------------------
        */

        $cashBankLine =
            DB::table('journal_details')
                ->where(
                    'journal_id',
                    $journal->id
                )
                ->where(
                    'account_id',
                    $cashBankAccountId
                )
                ->first();

        $this->assertNotNull(
            $cashBankLine,
            'Payment Voucher must use the selected cash/bank account.'
        );

        $this->assertEqualsWithDelta(
            0,
            (float) $cashBankLine->debit,
            0.01
        );

        $this->assertEqualsWithDelta(
            40000,
            (float) $cashBankLine->credit,
            0.01
        );

        /*
        |--------------------------------------------------------------------------
        | Balanced
        |--------------------------------------------------------------------------
        */

        $journal->load('details');

        $this->assertEqualsWithDelta(
            40000,
            (float) $journal->details->sum(
                'debit'
            ),
            0.01
        );

        $this->assertEqualsWithDelta(
            40000,
            (float) $journal->details->sum(
                'credit'
            ),
            0.01
        );
    }
    public function test_payment_voucher_rolls_back_when_company_accounting_mapping_is_missing(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Asset Account Group
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

        /*
        |--------------------------------------------------------------------------
        | Cash / Bank Account
        |--------------------------------------------------------------------------
        */

        $cashBankAccountId =
            DB::table('accounts')
                ->insertGetId([
                    'account_group_id' =>
                        $assetGroupId,

                    'code' =>
                        '1008-PV-RB',

                    'name' =>
                        'Bank Rollback Test',

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
        | Account Payable
        |--------------------------------------------------------------------------
        */

        $accountPayableId =
            DB::table('account_payables')
                ->insertGetId([
                    'reference_type' =>
                        'PURCHASE_INVOICE',

                    'reference_id' =>
                        999002,

                    'supplier_name' =>
                        'Supplier Payment Rollback',

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

        $beforeVouchers =
            DB::table('payment_vouchers')
                ->count();

        $beforeJournals =
            DB::table('journals')
                ->count();

        $apBefore =
            DB::table('account_payables')
                ->where(
                    'id',
                    $accountPayableId
                )
                ->first();

        $this->assertNotNull(
            $apBefore
        );

        /*
        |--------------------------------------------------------------------------
        | Act
        |--------------------------------------------------------------------------
        */

        try {

            app(PaymentVoucherService::class)
                ->create(
                    new PaymentVoucherDTO(
                        accountPayableId:
                            $accountPayableId,

                        cashBankAccountId:
                            $cashBankAccountId,

                        amount:
                            40000,

                        paymentMethod:
                            'BANK_TRANSFER',

                        remarks:
                            'Missing accounting mapping rollback test',

                        createdBy:
                            $this->data['user_id'],
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
        | Voucher Must Roll Back
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $beforeVouchers,
            DB::table('payment_vouchers')
                ->count()
        );

        /*
        |--------------------------------------------------------------------------
        | Journal Must Roll Back
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $beforeJournals,
            DB::table('journals')
                ->count()
        );

        /*
        |--------------------------------------------------------------------------
        | AP Must Be Restored
        |--------------------------------------------------------------------------
        */

        $apAfter =
            DB::table('account_payables')
                ->where(
                    'id',
                    $accountPayableId
                )
                ->first();

        $this->assertNotNull(
            $apAfter
        );

        $this->assertEqualsWithDelta(
            (float) $apBefore->paid_amount,
            (float) $apAfter->paid_amount,
            0.01
        );

        $this->assertEqualsWithDelta(
            (float) $apBefore->balance_amount,
            (float) $apAfter->balance_amount,
            0.01
        );

        $this->assertSame(
            $apBefore->status,
            $apAfter->status
        );
    }
}