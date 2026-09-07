<?php

namespace App\Services;

use App\DTO\JournalEntryDTO;
use App\DTO\JournalLineDTO;

class AutoJournalService
{
    public function __construct(
        private JournalPostingService $journalService,
        private AccountingAccountResolverService $accountResolverService,
    ) {
    }

    /**
     * Goods Receipt
     *
     * Dr Persediaan
     * Cr GRNI
     *
     * @param string|array $inventoryAccount
     */
    public function goodsReceipt(
        string|array $inventoryAccount,
        ?float $amount,
        int $referenceId,
        int $userId,
        int $companyId,
        ?string $journalDate = null,
    ): void {

        if (is_string($inventoryAccount)) {

            if ($amount === null) {
                throw new \InvalidArgumentException(
                    'Goods receipt journal amount is required.'
                );
            }

            $inventoryLines = [
                [
                    'accountCode' => $inventoryAccount,
                    'amount' => (float) $amount,
                ],
            ];

        } else {

            $inventoryLines = $inventoryAccount;
        }

        $aggregatedInventory = [];

        foreach ($inventoryLines as $line) {

            $accountCode =
                (string) (
                    $line['accountCode']
                    ?? ''
                );

            $lineAmount =
                (float) (
                    $line['amount']
                    ?? 0
                );

            if ($accountCode === '') {
                throw new \InvalidArgumentException(
                    'Goods receipt inventory account is required.'
                );
            }

            if ($lineAmount <= 0) {
                throw new \InvalidArgumentException(
                    'Goods receipt journal amount must be greater than zero.'
                );
            }

            if (!isset($aggregatedInventory[$accountCode])) {
                $aggregatedInventory[$accountCode] = 0.0;
            }

            $aggregatedInventory[$accountCode] += $lineAmount;
        }

        $journalLines = [];
        $totalAmount = 0.0;

        foreach (
            $aggregatedInventory
            as $accountCode => $inventoryAmount
        ) {

            $journalLines[] =
                new JournalLineDTO(
                    accountCode: $accountCode,
                    quantity: 0,
                    unitPrice: 0,
                    debit: $inventoryAmount,
                    credit: 0,
                    description: 'Inventory'
                );

            $totalAmount += $inventoryAmount;
        }

        if ($totalAmount <= 0) {
            throw new \InvalidArgumentException(
                'Goods receipt journal total amount must be greater than zero.'
            );
        }

        $grniAccount =
            $this
                ->accountResolverService
                ->grni(
                    $companyId
                );

        $journalLines[] =
            new JournalLineDTO(
                accountCode:
                    $grniAccount->code,

                quantity:
                    0,

                unitPrice:
                    0,

                debit:
                    0,

                credit:
                    $totalAmount,

                description:
                    'GRNI'
            );

        $entry =
            new JournalEntryDTO(
                referenceType: 'GOODS_RECEIPT',
                referenceId: $referenceId,
                description: 'Auto Journal Goods Receipt',
                createdBy: $userId,
                lines: $journalLines,
                journalDate: $journalDate,
            );

        $this
            ->journalService
            ->post($entry);
    }

    /**
     * Purchase Invoice
     *
     * Dr GRNI
     * Cr Hutang Dagang
     */
    public function purchaseInvoice(
        float $amount,
        int $referenceId,
        int $userId,
        int $companyId
    ) {
        $grniAccount =
            $this
                ->accountResolverService
                ->grni(
                    $companyId
                );

        $apAccount =
            $this
                ->accountResolverService
                ->accountsPayable(
                    $companyId
                );

        $entry =
            new JournalEntryDTO(
                referenceType: 'PURCHASE_INVOICE',
                referenceId: $referenceId,
                description: 'Auto Journal Purchase Invoice',
                createdBy: $userId,

                lines: [
                    new JournalLineDTO(
                        accountCode:$grniAccount->code,
                        quantity:0,
                        unitPrice:0,
                        debit:$amount,
                        credit:0,
                        description:'Reverse GRNI'
                    ),

                    new JournalLineDTO(
                        accountCode:$apAccount->code,
                        quantity:0,
                        unitPrice:0,
                        debit:0,
                        credit:$amount,
                        description:'Account Payable'
                    ),
                ]
            );

        return $this
            ->journalService
            ->post($entry);
    }

    public function paymentVoucher(
        float $amount,
        int $cashBankAccountId,
        int $referenceId,
        int $userId,
        int $companyId
    ) {
        $cashBankAccount =
            \App\Models\Account::findOrFail(
                $cashBankAccountId
            );

        $apAccount = $this
                    ->accountResolverService
                    ->accountsPayable(
                        $companyId
                    );
        $entry =
            new JournalEntryDTO(
                referenceType: 'PAYMENT_VOUCHER',
                referenceId: $referenceId,
                description: 'Payment Voucher',
                createdBy: $userId,

                lines: [
                    new JournalLineDTO(
                        accountCode: $apAccount->code,
                        quantity: 0,
                        unitPrice: 0,
                        debit: $amount,
                        credit: 0,
                        description: 'Hutang Dagang'
                    ),

                    new JournalLineDTO(
                        accountCode: $cashBankAccount->code,
                        quantity: 0,
                        unitPrice: 0,
                        debit: 0,
                        credit: $amount,
                        description: 'Kas/Bank'
                    ),
                ]
            );

        return $this
            ->journalService
            ->post($entry);
    }

    /**
     * Delivery Order / HPP
     *
     * Single line:
     *
     * Dr HPP
     * Cr Persediaan
     *
     * Multi line:
     *
     * Dr HPP A
     * Dr HPP B
     * Cr Inventory A
     * Cr Inventory B
     *
     * @param string|array $cogsAccount
     */
    public function deliveryOrder(
        string|array $cogsAccount,
        ?string $inventoryAccount,
        ?float $amount,
        int $referenceId,
        int $userId,
        ?string $journalDate = null,
    ) {

        /*
        |--------------------------------------------------------------------------
        | Normalize Lines
        |--------------------------------------------------------------------------
        */

        if (is_string($cogsAccount)) {

            if (
                $inventoryAccount === null
                ||
                $amount === null
            ) {
                throw new \InvalidArgumentException(
                    'Delivery order journal account and amount are required.'
                );
            }

            $transactionLines = [
                [
                    'cogsAccount' =>
                        $cogsAccount,

                    'inventoryAccount' =>
                        $inventoryAccount,

                    'amount' =>
                        (float) $amount,
                ],
            ];

        } else {

            $transactionLines =
                $cogsAccount;
        }

        /*
        |--------------------------------------------------------------------------
        | Aggregate COGS And Inventory Accounts
        |--------------------------------------------------------------------------
        */

        $aggregatedCogs = [];
        $aggregatedInventory = [];

        foreach ($transactionLines as $line) {

            $cogsCode =
                (string) (
                    $line['cogsAccount']
                    ?? ''
                );

            $inventoryCode =
                (string) (
                    $line['inventoryAccount']
                    ?? ''
                );

            $lineAmount =
                (float) (
                    $line['amount']
                    ?? 0
                );

            if (
                $cogsCode === ''
                ||
                $inventoryCode === ''
            ) {
                throw new \InvalidArgumentException(
                    'Delivery order journal accounts are required.'
                );
            }

            if ($lineAmount <= 0) {
                throw new \InvalidArgumentException(
                    'Delivery order journal amount must be greater than zero.'
                );
            }

            if (!isset($aggregatedCogs[$cogsCode])) {
                $aggregatedCogs[$cogsCode] = 0.0;
            }

            if (!isset($aggregatedInventory[$inventoryCode])) {
                $aggregatedInventory[$inventoryCode] = 0.0;
            }

            $aggregatedCogs[$cogsCode] += $lineAmount;

            $aggregatedInventory[$inventoryCode] += $lineAmount;
        }

        /*
        |--------------------------------------------------------------------------
        | Build Journal Detail Lines
        |--------------------------------------------------------------------------
        */

        $journalLines = [];

        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach (
            $aggregatedCogs
            as $accountCode => $cogsAmount
        ) {

            $journalLines[] =
                new JournalLineDTO(
                    accountCode: $accountCode,
                    debit: $cogsAmount,
                    credit: 0,
                    quantity: 0,
                    unitPrice: 0,
                    description: 'HPP'
                );

            $totalDebit += $cogsAmount;
        }

        foreach (
            $aggregatedInventory
            as $accountCode => $inventoryAmount
        ) {

            $journalLines[] =
                new JournalLineDTO(
                    accountCode: $accountCode,
                    debit: 0,
                    credit: $inventoryAmount,
                    quantity: 0,
                    unitPrice: 0,
                    description: 'Persediaan'
                );

            $totalCredit += $inventoryAmount;
        }

        if (
            $totalDebit <= 0
            ||
            $totalCredit <= 0
        ) {
            throw new \InvalidArgumentException(
                'Delivery order journal total amount must be greater than zero.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | One Journal Header Per Delivery Order
        |--------------------------------------------------------------------------
        */

        $entry =
            new JournalEntryDTO(
                referenceType: 'DELIVERY_ORDER',
                referenceId: $referenceId,
                description: 'Auto Journal Delivery Order',
                createdBy: $userId,
                lines: $journalLines,
                journalDate: $journalDate,
            );

        return $this
            ->journalService
            ->post($entry);
    }

    public function salesInvoice(
        string|array $salesAccount,
        ?float $amount,
        int $referenceId,
        int $userId,
        int $companyId,
    ) {
        /*
        |--------------------------------------------------------------------------
        | Resolve AR
        |--------------------------------------------------------------------------
        */

        $arAccount =
            $this
                ->accountResolverService
                ->accountsReceivable(
                    $companyId
                );

        /*
        |--------------------------------------------------------------------------
        | Normalize Lines
        |--------------------------------------------------------------------------
        */

        if (is_string($salesAccount)) {

            if ($amount === null) {
                throw new \InvalidArgumentException(
                    'Sales invoice journal amount is required.'
                );
            }

            $transactionLines = [
                [
                    'salesAccount' =>
                        $salesAccount,

                    'amount' =>
                        (float) $amount,
                ],
            ];

        } else {

            $transactionLines =
                $salesAccount;
        }

        /*
        |--------------------------------------------------------------------------
        | Aggregate Revenue Accounts
        |--------------------------------------------------------------------------
        */

        $aggregatedSales = [];
        $totalAmount = 0.0;

        foreach ($transactionLines as $line) {

            $salesCode =
                (string) (
                    $line['salesAccount']
                    ?? ''
                );

            $lineAmount =
                (float) (
                    $line['amount']
                    ?? 0
                );

            if ($salesCode === '') {
                throw new \InvalidArgumentException(
                    'Sales invoice journal sales account is required.'
                );
            }

            if ($lineAmount <= 0) {
                throw new \InvalidArgumentException(
                    'Sales invoice journal amount must be greater than zero.'
                );
            }

            if (!isset(
                $aggregatedSales[
                    $salesCode
                ]
            )) {
                $aggregatedSales[
                    $salesCode
                ] = 0.0;
            }

            $aggregatedSales[
                $salesCode
            ] += $lineAmount;

            $totalAmount +=
                $lineAmount;
        }

        /*
        |--------------------------------------------------------------------------
        | Build Journal Lines
        |--------------------------------------------------------------------------
        */

        $journalLines = [];

        $journalLines[] =
            new JournalLineDTO(
                accountCode:
                    $arAccount->code,

                debit:
                    $totalAmount,

                credit:
                    0,

                quantity:
                    0,

                unitPrice:
                    0,

                description:
                    'Piutang Dagang'
            );

        foreach (
            $aggregatedSales
            as $accountCode => $salesAmount
        ) {
            $journalLines[] =
                new JournalLineDTO(
                    accountCode:
                        $accountCode,

                    debit:
                        0,

                    credit:
                        $salesAmount,

                    quantity:
                        0,

                    unitPrice:
                        0,

                    description:
                        'Penjualan'
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Post Journal
        |--------------------------------------------------------------------------
        */

        $entry =
            new JournalEntryDTO(
                referenceType:
                    'SALES_INVOICE',

                referenceId:
                    $referenceId,

                description:
                    'Auto Journal Sales Invoice',

                createdBy:
                    $userId,

                lines:
                    $journalLines,
            );

        return $this
            ->journalService
            ->post(
                $entry
            );
    }

    public function customerReceipt(
        int $cashBankAccountId,
        float $amount,
        int $referenceId,
        int $userId,
        int $companyId,
    ) {
        /*
        |--------------------------------------------------------------------------
        | Resolve Cash / Bank
        |--------------------------------------------------------------------------
        */

        $cashBankAccount =
            \App\Models\Account::findOrFail(
                $cashBankAccountId
            );

        /*
        |--------------------------------------------------------------------------
        | Resolve Accounts Receivable
        |--------------------------------------------------------------------------
        */

        $arAccount =
            $this
                ->accountResolverService
                ->accountsReceivable(
                    $companyId
                );

        /*
        |--------------------------------------------------------------------------
        | Journal
        |--------------------------------------------------------------------------
        */

        $entry =
            new JournalEntryDTO(
                referenceType:
                    'CUSTOMER_RECEIPT',

                referenceId:
                    $referenceId,

                description:
                    'Customer Receipt',

                createdBy:
                    $userId,

                lines: [
                    new JournalLineDTO(
                        accountCode:
                            $cashBankAccount->code,

                        debit:
                            $amount,

                        credit:
                            0,

                        quantity:
                            0,

                        unitPrice:
                            0,

                        description:
                            'Kas / Bank'
                    ),

                    new JournalLineDTO(
                        accountCode:
                            $arAccount->code,

                        debit:
                            0,

                        credit:
                            $amount,

                        quantity:
                            0,

                        unitPrice:
                            0,

                        description:
                            'Piutang Dagang'
                    ),
                ]
            );

        return $this
            ->journalService
            ->post(
                $entry
            );
    }
}
