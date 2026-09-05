<?php

namespace App\Services;

use App\DTO\JournalEntryDTO;
use App\DTO\JournalLineDTO;

class AutoJournalService
{
    public function __construct(
        private JournalPostingService $journalService
    ) {}

    /**
     * Goods Receipt
     *
     * Dr Persediaan
     * Cr GRNI
     */
    
    public function goodsReceipt(
        string $inventoryAccount,
        float $amount,
        int $referenceId,
        int $userId,
        ?string $journalDate = null,
    ): void {

        $entry = new JournalEntryDTO(
            referenceType : 'GOODS_RECEIPT',
            referenceId   : $referenceId,
            description   : 'Auto Journal Goods Receipt',
            createdBy     : $userId,

            lines : [

                new JournalLineDTO(
                    accountCode : $inventoryAccount,
                    quantity    : 0,
                    unitPrice   : 0,
                    debit       : $amount,
                    credit      : 0,
                    description : 'Inventory'
                ),

                new JournalLineDTO(
                    accountCode : '2101',
                    quantity    : 0,
                    unitPrice   : 0,
                    debit       : 0,
                    credit      : $amount,
                    description : 'GRNI'
                ),
            ],
            journalDate: $journalDate,
        );

        $this->journalService->post($entry);
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
        int $userId
    )
    {
        $entry = new JournalEntryDTO(
            referenceType : 'PURCHASE_INVOICE',
            referenceId   : $referenceId,
            description   : 'Auto Journal Purchase Invoice',
            createdBy     : $userId,

            lines : [

                new JournalLineDTO(
                    accountCode : '2101', // GRNI
                    quantity    : 0,
                    unitPrice   : 0,
                    debit       : $amount,
                    credit      : 0,
                    description : 'Reverse GRNI'
                ),

                new JournalLineDTO(
                    accountCode : '2001', // Hutang Dagang
                    quantity    : 0,
                    unitPrice   : 0,
                    debit       : 0,
                    credit      : $amount,
                    description : 'Account Payable'
                ),
            ]
        );

        return $this->journalService->post($entry);
    }

    public function paymentVoucher(
        float $amount,
        int $cashBankAccountId,
        int $referenceId,
        int $userId
    )
    {
        $cashBankAccount =
            \App\Models\Account::findOrFail(
                $cashBankAccountId
            );

        $entry = new JournalEntryDTO(
            referenceType : 'PAYMENT_VOUCHER',
            referenceId   : $referenceId,
            description   : 'Payment Voucher',
            createdBy     : $userId,

            lines : [

                new JournalLineDTO(
                    accountCode : '2001',
                    quantity    : 0,
                    unitPrice   : 0,
                    debit       : $amount,
                    credit      : 0,
                    description : 'Hutang Dagang'
                ),

                new JournalLineDTO(
                    accountCode : $cashBankAccount->code,
                    quantity    : 0,
                    unitPrice   : 0,
                    debit       : 0,
                    credit      : $amount,
                    description : 'Kas/Bank'
                ),
            ]
        );

        return $this->journalService
            ->post($entry);
    }

    //Auto Journal HPP
    public function deliveryOrder(
        string $cogsAccount,
        string $inventoryAccount,
        float $amount,
        int $referenceId,
        int $userId,
        ?string $journalDate = null,
    )
    {
        $entry = new JournalEntryDTO(

            referenceType : 'DELIVERY_ORDER',
            referenceId   : $referenceId,
            description   : 'Auto Journal Delivery Order',
            createdBy     : $userId,

            lines : [

                new JournalLineDTO(
                    accountCode : $cogsAccount,
                    debit       : $amount,
                    credit      : 0,
                    quantity    : 0,
                    unitPrice   : 0,
                    description : 'HPP'
                ),

                new JournalLineDTO(
                    accountCode : $inventoryAccount,
                    debit       : 0,
                    credit      : $amount,
                    quantity    : 0,
                    unitPrice   : 0,
                    description : 'Persediaan'
                ),
            ],
            journalDate: $journalDate,
        );

        return $this->journalService
            ->post($entry);
    }

    public function salesInvoice(
        string $salesAccount,
        float $amount,
        int $referenceId,
        int $userId
    )
    {
        $entry = new JournalEntryDTO(

            referenceType : 'SALES_INVOICE',

            referenceId : $referenceId,

            description : 'Auto Journal Sales Invoice',

            createdBy : $userId,

            lines : [

                new JournalLineDTO(

                    accountCode : '1101',

                    debit : $amount,

                    credit : 0,

                    quantity : 0,

                    unitPrice : 0,

                    description : 'Piutang Dagang'
                ),

                new JournalLineDTO(

                    accountCode : $salesAccount,

                    debit : 0,

                    credit : $amount,

                    quantity : 0,

                    unitPrice : 0,

                    description : 'Penjualan'
                ),
            ]
        );

        return $this->journalService
            ->post($entry);
    }

    public function customerReceipt(
        int $cashBankAccountId,
        float $amount,
        int $referenceId,
        int $userId
    )
    {
        $cashBankAccount =
            \App\Models\Account::findOrFail(
                $cashBankAccountId
            );

        $entry = new JournalEntryDTO(

            referenceType : 'CUSTOMER_RECEIPT',

            referenceId : $referenceId,

            description : 'Customer Receipt',

            createdBy : $userId,

            lines : [

                new JournalLineDTO(

                    accountCode :
                        $cashBankAccount->code,

                    debit :
                        $amount,

                    credit :
                        0,

                    quantity :
                        0,

                    unitPrice :
                        0,

                    description :
                        'Kas / Bank'
                ),

                new JournalLineDTO(

                    accountCode :
                        '1101',

                    debit :
                        0,

                    credit :
                        $amount,

                    quantity :
                        0,

                    unitPrice :
                        0,

                    description :
                        'Piutang Dagang'
                ),
            ]
        );

        return $this->journalService
            ->post($entry);
    }   
    
}