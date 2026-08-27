<?php

namespace App\Repositories\Eloquent;

use App\DTO\FinanceDashboardFilterDTO;
use App\Models\Account;
use App\Models\Item;
use App\Models\StockLedger;
use App\Repositories\Contracts\FinanceDashboardRepositoryInterface;

class FinanceDashboardRepository
implements FinanceDashboardRepositoryInterface
{
    /**
     * Cash + Bank position.
     *
     * Semua akun 1000-1099 dianggap cash/bank account.
     */
    public function getCashPosition(
        FinanceDashboardFilterDTO $dto
    ) {
        return Account::query()

            ->whereBetween(
                'code',
                [
                    '1001',
                    '1099',
                ]
            )

            ->with([
                'group',
            ])

            ->withSum([
                'journalDetails as total_debit' => function ($q) use ($dto) {

                    $q->whereHas(
                        'journal',
                        function ($journal) use ($dto) {

                            $journal->where(
                                'journal_date',
                                '<=',
                                $dto->dateTo
                            );
                        }
                    );
                }
            ], 'debit')

            ->withSum([
                'journalDetails as total_credit' => function ($q) use ($dto) {

                    $q->whereHas(
                        'journal',
                        function ($journal) use ($dto) {

                            $journal->where(
                                'journal_date',
                                '<=',
                                $dto->dateTo
                            );
                        }
                    );
                }
            ], 'credit')

            ->orderBy('code')

            ->get()

            ->map(function ($account) {

                $debit =
                    (float) $account->total_debit;

                $credit =
                    (float) $account->total_credit;

                return [

                    'account_id' =>
                        $account->id,

                    'code' =>
                        $account->code,

                    'name' =>
                        $account->name,

                    'balance' =>
                        $debit - $credit,
                ];
            });
    }

    /**
     * Inventory valuation.
     *
     * Mengambil saldo terakhir StockLedger
     * per warehouse + item, lalu dikalikan
     * average_cost item.
     */
    public function getInventoryValue()
    {
        $latestIds =
            StockLedger::query()

                ->selectRaw(
                    'MAX(id)'
                )

                ->groupBy(
                    'warehouse_id',
                    'item_id'
                );

        $latestLedgers =
            StockLedger::query()

                ->with('item')

                ->whereIn(
                    'id',
                    $latestIds
                )

                ->get();

        $totalValue = 0;

        $items = [];

        foreach ($latestLedgers as $ledger) {

            $balanceQty =
                (float) $ledger->balance_qty;

            $averageCost =
                (float) (
                    $ledger
                        ->item
                        ->average_cost
                    ?? 0
                );

            $value =
                $balanceQty
                *
                $averageCost;

            $totalValue += $value;

            $items[] = [

                'warehouse_id' =>
                    $ledger->warehouse_id,

                'item_id' =>
                    $ledger->item_id,

                'item_code' =>
                    $ledger->item->code,

                'item_name' =>
                    $ledger->item->name,

                'balance_qty' =>
                    $balanceQty,

                'average_cost' =>
                    $averageCost,

                'inventory_value' =>
                    $value,
            ];
        }

        return [

            'total_value' =>
                $totalValue,

            'items' =>
                $items,
        ];
    }

    /**
     * Low stock.
     *
     * Membandingkan balance stok terakhir
     * dengan minimum_stock.
     */
    public function getLowStockItems()
    {
        $latestIds =
            StockLedger::query()

                ->selectRaw(
                    'MAX(id)'
                )

                ->groupBy(
                    'warehouse_id',
                    'item_id'
                );

        $stockRows =
            StockLedger::query()

                ->with('item')

                ->whereIn(
                    'id',
                    $latestIds
                )

                ->get();

        $lowStock = [];

        foreach ($stockRows as $stock) {

            $item =
                $stock->item;

            $balanceQty =
                (float) $stock->balance_qty;

            $minimumStock =
                (float) $item->minimum_stock;

            if (
                $balanceQty
                <
                $minimumStock
            ) {

                $lowStock[] = [

                    'warehouse_id' =>
                        $stock->warehouse_id,

                    'item_id' =>
                        $item->id,

                    'code' =>
                        $item->code,

                    'name' =>
                        $item->name,

                    'balance_qty' =>
                        $balanceQty,

                    'minimum_stock' =>
                        $minimumStock,

                    'shortage' =>
                        $minimumStock
                        -
                        $balanceQty,
                ];
            }
        }

        return $lowStock;
    }

    /**
     * Bank accounts with negative balance.
     *
     * Khusus akun 1002 dst yang negatif.
     */
    public function getBankNegativeAccounts(
        FinanceDashboardFilterDTO $dto
    ) {
        return Account::query()

            ->whereBetween(
                'code',
                [
                    '1002',
                    '1099',
                ]
            )

            ->withSum([
                'journalDetails as total_debit' => function ($q) use ($dto) {

                    $q->whereHas(
                        'journal',
                        function ($journal) use ($dto) {

                            $journal->where(
                                'journal_date',
                                '<=',
                                $dto->dateTo
                            );
                        }
                    );
                }
            ], 'debit')

            ->withSum([
                'journalDetails as total_credit' => function ($q) use ($dto) {

                    $q->whereHas(
                        'journal',
                        function ($journal) use ($dto) {

                            $journal->where(
                                'journal_date',
                                '<=',
                                $dto->dateTo
                            );
                        }
                    );
                }
            ], 'credit')

            ->get()

            ->map(function ($account) {

                $balance =
                    (float) $account->total_debit
                    -
                    (float) $account->total_credit;

                return [

                    'code' =>
                        $account->code,

                    'name' =>
                        $account->name,

                    'balance' =>
                        $balance,
                ];
            })

            ->filter(
                fn ($row) =>
                    $row['balance'] < 0
            )

            ->values();
    }
}