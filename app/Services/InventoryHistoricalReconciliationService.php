<?php

namespace App\Services;

use App\DTO\InventoryHistoricalReconciliationDTO;
use App\Repositories\Contracts\InventoryHistoricalReconciliationRepositoryInterface;
use Carbon\Carbon;

class InventoryHistoricalReconciliationService
{
    public function __construct(

        private InventoryHistoricalReconciliationRepositoryInterface $repository,

    ) {}

    public function reconcile(
        InventoryHistoricalReconciliationDTO $dto
    ) {
        /*
        |--------------------------------------------------------------------------
        | LOAD DATA
        |--------------------------------------------------------------------------
        */

        $stockLedgers =
            $this->repository
                ->getStockLedgers($dto);

        $goodsReceipts =
            $this->repository
                ->getGoodsReceipts($dto);

        $deliveryOrders =
            $this->repository
                ->getDeliveryOrders($dto);

        $journals =
            $this->repository
                ->getInventoryJournals($dto);

        /*
        |--------------------------------------------------------------------------
        | EXPECTED INVENTORY STATE
        |--------------------------------------------------------------------------
        |
        | Kita hitung ulang moving average berdasarkan histori.
        |--------------------------------------------------------------------------
        */

        $qty = 0.0;

        $value = 0.0;

        $rows = [];

        foreach (
            $stockLedgers
            as $ledger
        ) {

            $qtyIn =
                (float) $ledger->qty_in;

            $qtyOut =
                (float) $ledger->qty_out;

            $postedUnitCost =
                (float) $ledger->unit_cost;

            $postedTotalCost =
                (float) $ledger->total_cost;

            /*
            |--------------------------------------------------------------------------
            | EXPECTED COST
            |--------------------------------------------------------------------------
            */

            $expectedUnitCost = 0.0;

            $expectedTotalCost = 0.0;

            $oldAverageCost =
                $qty > 0
                    ? $value / $qty
                    : 0;

            /*
            |--------------------------------------------------------------------------
            | INBOUND
            |--------------------------------------------------------------------------
            */

            if ($qtyIn > 0) {

                $expectedUnitCost =
                    $postedUnitCost;

                $expectedTotalCost =
                    $qtyIn
                    *
                    $expectedUnitCost;

                $qty +=
                    $qtyIn;

                $value +=
                    $expectedTotalCost;
            }

            /*
            |--------------------------------------------------------------------------
            | OUTBOUND
            |--------------------------------------------------------------------------
            */

            elseif ($qtyOut > 0) {

                $expectedUnitCost =
                    $oldAverageCost;

                $expectedTotalCost =
                    $qtyOut
                    *
                    $expectedUnitCost;

                $qty -=
                    $qtyOut;

                $value -=
                    $expectedTotalCost;
            }

            /*
            |--------------------------------------------------------------------------
            | CLEAN FLOATING POINT
            |--------------------------------------------------------------------------
            */

            if (
                abs($qty) < 0.000001
            ) {
                $qty = 0;
            }

            if (
                abs($value) < 0.000001
            ) {
                $value = 0;
            }

            /*
            |--------------------------------------------------------------------------
            | EXISTING JOURNAL
            |--------------------------------------------------------------------------
            */

            // $journalAmount =
            //     $this->findJournalAmount(
            //         $journals,
            //         $ledger->reference_type,
            //         $ledger->reference_id
            //     );
            $journalAmount =
            $this->findJournalAmount(
                $journals,
                $ledger->reference_type,
                $ledger->reference_id,
                $dto->inventoryAccountId
            );

            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */

            $status =
                'MATCH';

            if (
                in_array(
                    $ledger->reference_type,
                    [
                        'DELIVERY_ORDER',
                        'GOODS_RECEIPT',
                    ],
                    true
                )
            ) {

                if (
                    $journalAmount === null
                ) {

                    $status =
                        'MISSING_JOURNAL';

                } elseif (
                    abs(
                        $journalAmount
                        -
                        $expectedTotalCost
                    ) > 0.01
                ) {

                    $status =
                        'COST_MISMATCH';
                }
            }

            /*
            |--------------------------------------------------------------------------
            | DIFFERENCE
            |--------------------------------------------------------------------------
            */

            $journalDifference =
                $journalAmount === null
                    ? $expectedTotalCost
                    : $journalAmount
                      -
                      $expectedTotalCost;

            /*
            |--------------------------------------------------------------------------
            | APPEND
            |--------------------------------------------------------------------------
            */

            $rows[] = [

                'stock_ledger_id' =>
                    $ledger->id,

                'transaction_date' =>
                    $ledger->transaction_date,

                'reference_type' =>
                    $ledger->reference_type,

                'reference_id' =>
                    $ledger->reference_id,

                'qty_in' =>
                    $qtyIn,

                'qty_out' =>
                    $qtyOut,

                'old_average_cost' =>
                    round(
                        $oldAverageCost,
                        2
                    ),

                'expected_unit_cost' =>
                    round(
                        $expectedUnitCost,
                        2
                    ),

                'expected_total_cost' =>
                    round(
                        $expectedTotalCost,
                        2
                    ),

                'posted_unit_cost' =>
                    round(
                        $postedUnitCost,
                        2
                    ),

                'posted_total_cost' =>
                    round(
                        $postedTotalCost,
                        2
                    ),

                'journal_amount' =>
                    $journalAmount === null
                        ? null
                        : round(
                            $journalAmount,
                            2
                        ),

                'journal_difference' =>
                    round(
                        $journalDifference,
                        2
                    ),

                'status' =>
                    $status,

                'balance_qty_after' =>
                    round(
                        $qty,
                        4
                    ),

                'inventory_value_after' =>
                    round(
                        $value,
                        2
                    ),
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | ORPHAN JOURNALS
        |--------------------------------------------------------------------------
        */
        $orphanJournals = [];

        foreach (
            $journals
            as $journal
        ) {

            /*
            |--------------------------------------------------------------------------
            | REVERSAL JOURNAL ITSELF IS NOT AN ORPHAN SOURCE
            |--------------------------------------------------------------------------
            */

            if (
                $journal->journal_purpose
                ===
                'REVERSAL'
            ) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | DOCUMENT HAS STOCK LEDGER
            |--------------------------------------------------------------------------
            */

            if (
                $this->hasMatchingStockLedger(
                    $stockLedgers,
                    $journal->reference_type,
                    $journal->reference_id
                )
            ) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | ALREADY REVERSED
            |--------------------------------------------------------------------------
            |
            | Jika journal ini sudah mempunyai reversal,
            | jangan laporkan lagi sebagai orphan.
            |
            */

            $alreadyReversed =
                $journals->contains(
                    function ($candidate)
                    use (
                        $journal
                    ) {

                        return
                            $candidate->journal_purpose
                            ===
                            'REVERSAL'
                            &&
                            (int)
                            $candidate->source_journal_id
                            ===
                            (int)
                            $journal->id;
                    }
                );

            if ($alreadyReversed) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | INVENTORY AMOUNT
            |--------------------------------------------------------------------------
            */

            $journalAmount =
                $this->getJournalInventoryAmount(
                    $journal,
                    $dto->inventoryAccountId
                );

            $orphanJournals[] = [

                'journal_id' => $journal->id,
                'journal_no' => $journal->journal_no,
                'journal_date' => $journal->journal_date,
                'reference_type' => $journal->reference_type,
                'reference_id' => $journal->reference_id,
                'journal_purpose' => $journal->journal_purpose,
                'inventory_amount' =>
                    round(
                        abs($journalAmount),
                        2
                    ),
                'status' =>
                    'ORPHAN_JOURNAL',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | SUMMARY
        |--------------------------------------------------------------------------
        */

        $missingJournals =
            collect($rows)
                ->where(
                    'status',
                    'MISSING_JOURNAL'
                );

        $costMismatches =
            collect($rows)
                ->where(
                    'status',
                    'COST_MISMATCH'
                );

        $totalExpectedOutbound =
            collect($rows)
                ->where(
                    'qty_out',
                    '>',
                    0
                )
                ->sum(
                    'expected_total_cost'
                );

        $totalPostedOutbound =
            collect($rows)
                ->where(
                    'qty_out',
                    '>',
                    0
                )
                ->sum(
                    'journal_amount'
                );

        $totalExpectedInbound =
            collect($rows)
                ->where(
                    'qty_in',
                    '>',
                    0
                )
                ->sum(
                    'expected_total_cost'
                );

        $totalPostedInbound =
            collect($rows)
                ->where(
                    'qty_in',
                    '>',
                    0
                )
                ->sum(
                    'journal_amount'
                );

        return [

            'warehouse_id' =>
                $dto->warehouseId,

            'item_id' =>
                $dto->itemId,

            'date_from' =>
                $dto->dateFrom,

            'date_to' =>
                $dto->dateTo,

            /*
            |--------------------------------------------------------------------------
            | FINAL COSTING STATE
            |--------------------------------------------------------------------------
            */

            'final_qty' =>
                round(
                    $qty,
                    4
                ),

            'final_value' =>
                round(
                    $value,
                    2
                ),

            'final_average_cost' =>
                $qty > 0
                    ? round(
                        $value / $qty,
                        2
                    )
                    : 0,

            /*
            |--------------------------------------------------------------------------
            | TRANSACTION DETAIL
            |--------------------------------------------------------------------------
            */

            'rows' =>
                $rows,

            /*
            |--------------------------------------------------------------------------
            | ORPHAN JOURNALS
            |--------------------------------------------------------------------------
            */

            'orphan_journals' =>
                $orphanJournals,

            /*
            |--------------------------------------------------------------------------
            | SUMMARY
            |--------------------------------------------------------------------------
            */

            'missing_journal_count' =>
                $missingJournals->count(),

            'cost_mismatch_count' =>
                $costMismatches->count(),

            'orphan_journal_count' =>
                count(
                    $orphanJournals
                ),

            'total_expected_inbound' =>
                round(
                    $totalExpectedInbound,
                    2
                ),

            'total_posted_inbound' =>
                round(
                    $totalPostedInbound,
                    2
                ),

            'total_expected_outbound' =>
                round(
                    $totalExpectedOutbound,
                    2
                ),

            'total_posted_outbound' =>
                round(
                    $totalPostedOutbound,
                    2
                ),

            'is_reconciled' =>
                $missingJournals->isEmpty()
                &&
                $costMismatches->isEmpty()
                &&
                empty($orphanJournals),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | FIND JOURNAL AMOUNT
    |--------------------------------------------------------------------------
    */

    // protected function findJournalAmount(
    //     $journals,
    //     string $referenceType,
    //     int $referenceId
    // ): ?float {

    //     $journal =
    //         $journals->first(
    //             function ($journal)
    //             use (
    //                 $referenceType,
    //                 $referenceId
    //             ) {

    //                 return
    //                     $journal->reference_type
    //                     ===
    //                     $referenceType
    //                     &&
    //                     (int)
    //                     $journal->reference_id
    //                     ===
    //                     $referenceId;
    //             }
    //         );

    //     if (! $journal) {
    //         return null;
    //     }

    //     return $this->getJournalInventoryAmount($journal);
    // }
    protected function findJournalAmount(
        $journals,
        string $referenceType,
        int $referenceId,
        int $inventoryAccountId
    ): ?float {

        $matchingJournals =
            $journals->filter(
                function ($journal)
                use (
                    $referenceType,
                    $referenceId
                ) {

                    return
                        $journal->reference_type === $referenceType
                        &&
                        (int) $journal->reference_id === $referenceId;
                }
            );

        if ($matchingJournals->isEmpty()) {
            return null;
        }

        return (float)
            $matchingJournals->sum(
                function ($journal)
                use (
                    $inventoryAccountId
                ) {

                    return
                        $this->getJournalInventoryAmount(
                            $journal,
                            $inventoryAccountId
                        );
                }
            );
    }

    /*
    |--------------------------------------------------------------------------
    | GET INVENTORY AMOUNT
    |--------------------------------------------------------------------------
    */

    // protected function getJournalInventoryAmount(
    //     $journal
    // ): float {

    //     return (float)
    //         $journal->details
    //             ->sum(function ($detail) {

    //                 return
    //                     (float)
    //                     $detail->debit
    //                     -
    //                     (float)
    //                     $detail->credit;
    //             });
    // }

    protected function getJournalInventoryAmount(
        $journal,
        int $inventoryAccountId
    ): float {

        $details =
            $journal->details
                ->where(
                    'account_id',
                    $inventoryAccountId
                );

        if ($details->isEmpty()) {
            return 0.0;
        }

        $debit =
            (float)
            $details->sum('debit');

        $credit =
            (float)
            $details->sum('credit');

        /*
        |--------------------------------------------------------------------------
        | DELIVERY ORDER
        |--------------------------------------------------------------------------
        |
        | Normal:
        |
        | Dr HPP
        | Cr Inventory
        |
        | Jadi credit Inventory = positive effective posting.
        |
        | Reversal:
        |
        | Dr Inventory
        | Cr HPP
        |
        | Maka hasil menjadi negative dan otomatis mengurangi posting asli.
        |
        */

        if (
            $journal->reference_type
            ===
            'DELIVERY_ORDER'
        ) {

            return
                $credit
                -
                $debit;
        }

        /*
        |--------------------------------------------------------------------------
        | GOODS RECEIPT
        |--------------------------------------------------------------------------
        |
        | Normal:
        |
        | Dr Inventory
        | Cr GRNI
        |
        | Debit Inventory = positive effective posting.
        |
        | Reversal:
        |
        | Dr GRNI
        | Cr Inventory
        |
        | Maka hasil menjadi negative.
        |
        */

        if (
            $journal->reference_type
            ===
            'GOODS_RECEIPT'
        ) {

            return
                $debit
                -
                $credit;
        }

        /*
        |--------------------------------------------------------------------------
        | DEFAULT
        |--------------------------------------------------------------------------
        */

        return
            $debit
            -
            $credit;
    }

    /*
    |--------------------------------------------------------------------------
    | HAS STOCK LEDGER
    |--------------------------------------------------------------------------
    */

    protected function hasMatchingStockLedger(
        $stockLedgers,
        string $referenceType,
        int $referenceId
    ): bool {

        return $stockLedgers->contains(
            function ($ledger)
            use (
                $referenceType,
                $referenceId
            ) {

                return
                    $ledger->reference_type
                    ===
                    $referenceType
                    &&
                    (int)
                    $ledger->reference_id
                    ===
                    $referenceId;
            }
        );
    }
}