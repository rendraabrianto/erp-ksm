@extends('adminlte::page')

@section('title', 'Stock Ledger')

@section('content_header')
    <h1>Stock Ledger</h1>
@stop

@section('content')

    <div class="card card-primary">

        <div class="card-header">
            <h3 class="card-title">
                Filter Stock Ledger
            </h3>
        </div>

        <form
            method="GET"
            action="{{ route('erp.inventory.stock-ledger.index') }}"
        >

            <div class="card-body">

                <div class="row">

                    <div class="col-md-3">
                        <div class="form-group">

                            <label>
                                Warehouse
                            </label>

                            <select
                                name="warehouse_id"
                                class="form-control"
                            >

                                <option value="">
                                    -- Semua Warehouse --
                                </option>

                                @foreach($warehouses as $warehouse)

                                    <option
                                        value="{{ $warehouse->id }}"
                                        @selected(
                                            request('warehouse_id')
                                            == $warehouse->id
                                        )
                                    >
                                        {{ $warehouse->code }}
                                        -
                                        {{ $warehouse->name }}
                                    </option>

                                @endforeach

                            </select>

                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">

                            <label>
                                Item
                            </label>

                            <select
                                name="item_id"
                                class="form-control"
                            >

                                <option value="">
                                    -- Semua Item --
                                </option>

                                @foreach($items as $item)

                                    <option
                                        value="{{ $item->id }}"
                                        @selected(
                                            request('item_id')
                                            == $item->id
                                        )
                                    >
                                        {{ $item->code }}
                                        -
                                        {{ $item->name }}
                                    </option>

                                @endforeach

                            </select>

                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">

                            <label>
                                Date From
                            </label>

                            <input
                                type="date"
                                name="date_from"
                                class="form-control"
                                value="{{ request('date_from') }}"
                            >

                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">

                            <label>
                                Date To
                            </label>

                            <input
                                type="date"
                                name="date_to"
                                class="form-control"
                                value="{{ request('date_to') }}"
                            >

                        </div>
                    </div>

                </div>

            </div>

            <div class="card-footer">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    <i class="fas fa-search mr-1"></i>
                    Filter
                </button>

                <a
                    href="{{ route('erp.inventory.stock-ledger.index') }}"
                    class="btn btn-secondary"
                >
                    <i class="fas fa-sync-alt mr-1"></i>
                    Reset
                </a>

            </div>

        </form>

    </div>

    <div class="card">

        <div class="card-header">
            <h3 class="card-title">
                Stock Ledger Transactions
            </h3>
        </div>

        <div class="card-body table-responsive">

            <table
                id="stock-ledger-table"
                class="table table-bordered table-hover table-sm"
            >

                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Warehouse</th>
                        <th>Item</th>
                        <th>Reference</th>
                        <th class="text-right">
                            Qty In
                        </th>
                        <th class="text-right">
                            Qty Out
                        </th>
                        <th class="text-right">
                            Balance Qty
                        </th>
                        <th class="text-right">
                            Unit Cost
                        </th>
                        <th class="text-right">
                            Avg Cost
                        </th>

                        <th class="text-right">
                            Inventory Value
                        </th>
                        <th class="text-right">
                            Total Cost
                        </th>
                        <th>Remarks</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse($ledgers as $row)
                        @php
                            $ledger = $row['ledger'];
                        @endphp

                        <tr>

                            <td>
                                {{ \Illuminate\Support\Carbon::parse(
                                    $ledger->transaction_date
                                )->format('d-m-Y') }}
                            </td>

                            <td>
                                {{ $ledger->warehouse?->code }}
                                -
                                {{ $ledger->warehouse?->name }}
                            </td>

                            <td>
                                <strong>
                                    {{ $ledger->item?->code }}
                                </strong>
                                <br>
                                <small class="text-muted">
                                    {{ $ledger->item?->name }}
                                </small>
                            </td>

                            <td>
                                {{ $ledger->reference_type }}
                                #{{ $ledger->reference_id }}
                            </td>

                            <td class="text-right">
                                {{ number_format(
                                    (float) $ledger->qty_in,
                                    2,
                                    ',',
                                    '.'
                                ) }}
                            </td>

                            <td class="text-right">
                                {{ number_format(
                                    (float) $ledger->qty_out,
                                    2,
                                    ',',
                                    '.'
                                ) }}
                            </td>

                            <td class="text-right">
                                <strong>
                                    {{ number_format(
                                        (float) $ledger->balance_qty,
                                        2,
                                        ',',
                                        '.'
                                    ) }}
                                </strong>
                            </td>

                            <td class="text-right">
                                Rp {{ number_format(
                                    (float) $ledger->unit_cost,
                                    2,
                                    ',',
                                    '.'
                                ) }}
                            </td>

                            <td class="text-right">
                                Rp {{ number_format(
                                    $row['calculated_average_cost'],
                                    2,
                                    ',',
                                    '.'
                                ) }}
                            </td>

                            <td class="text-right">
                                Rp {{ number_format(
                                    $row['calculated_inventory_value'],
                                    2,
                                    ',',
                                    '.'
                                ) }}
                            </td>

                            <td class="text-right">
                                Rp {{ number_format(
                                    (float) $ledger->total_cost,
                                    2,
                                    ',',
                                    '.'
                                ) }}
                            </td>

                            <td>
                                {{ $ledger->remarks ?? '-' }}
                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td
                                colspan="10"
                                class="text-center"
                            >
                                Tidak ada data stock ledger.
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>

@stop

@section('js')

<script>
    $(function () {

        $('#stock-ledger-table').DataTable({
            responsive: true,
            autoWidth: false,
            pageLength: 25,
            order: [],
        });

    });
</script>

@stop