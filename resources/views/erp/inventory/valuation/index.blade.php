@extends('adminlte::page')

@section('title', 'Inventory Valuation')

@section('content_header')
    <h1>Inventory Valuation</h1>
@stop

@section('content')

    {{-- Filter --}}
    <div class="card card-primary">

        <div class="card-header">
            <h3 class="card-title">
                Filter Inventory Valuation
            </h3>
        </div>

        <form
            method="GET"
            action="{{ route('erp.inventory.valuation.index') }}"
        >

            <div class="card-body">

                <div class="row">

                    <div class="col-md-4">

                        <div class="form-group">

                            <label>Warehouse</label>

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

                    <div class="col-md-4">

                        <div class="form-group">

                            <label>Item</label>

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

                    <div class="col-md-4">

                        <div class="form-group">

                            <label>As Of Date</label>

                            <input
                                type="date"
                                name="as_of_date"
                                class="form-control"
                                value="{{ request('as_of_date') }}"
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
                    href="{{ route(
                        'erp.inventory.valuation.index'
                    ) }}"
                    class="btn btn-secondary"
                >
                    <i class="fas fa-sync-alt mr-1"></i>
                    Reset
                </a>

            </div>

        </form>

    </div>

    {{-- Summary --}}
    <div class="row">

        <div class="col-md-4">

            <div class="info-box">

                <span class="info-box-icon bg-info">
                    <i class="fas fa-boxes"></i>
                </span>

                <div class="info-box-content">

                    <span class="info-box-text">
                        Total Item
                    </span>

                    <span class="info-box-number">
                        {{ number_format(
                            $result['total_items']
                        ) }}
                    </span>

                </div>

            </div>

        </div>

        <div class="col-md-4">

            <div class="info-box">

                <span class="info-box-icon bg-primary">
                    <i class="fas fa-cubes"></i>
                </span>

                <div class="info-box-content">

                    <span class="info-box-text">
                        Total Qty
                    </span>

                    <span class="info-box-number">
                        {{ number_format(
                            $result['total_qty'],
                            2,
                            ',',
                            '.'
                        ) }}
                    </span>

                </div>

            </div>

        </div>

        <div class="col-md-4">

            <div class="info-box">

                <span class="info-box-icon bg-success">
                    <i class="fas fa-money-bill-wave"></i>
                </span>

                <div class="info-box-content">

                    <span class="info-box-text">
                        Total Inventory Value
                    </span>

                    <span class="info-box-number">
                        Rp {{ number_format(
                            $result[
                                'total_inventory_value'
                            ],
                            2,
                            ',',
                            '.'
                        ) }}
                    </span>

                </div>

            </div>

        </div>

    </div>

    {{-- Valuation Table --}}
    <div class="card">

        <div class="card-header">

            <h3 class="card-title">
                Inventory Valuation
            </h3>

            @if($result['as_of_date'])

                <div class="card-tools">

                    <span class="badge badge-info">

                        As Of:
                        {{ \Illuminate\Support\Carbon::parse(
                            $result['as_of_date']
                        )->format('d-m-Y') }}

                    </span>

                </div>

            @endif

        </div>

        <div class="card-body table-responsive">

            <table
                id="inventory-valuation-table"
                class="table table-bordered table-hover"
            >

                <thead>

                    <tr>

                        <th>Warehouse</th>
                        <th>Item</th>

                        <th class="text-right">
                            Qty On Hand
                        </th>

                        <th class="text-right">
                            Average Cost
                        </th>

                        <th class="text-right">
                            Inventory Value
                        </th>

                        <th>
                            Last Transaction
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                </thead>

                <tbody>

                    @forelse($result['rows'] as $row)

                        <tr>

                            <td>
                                {{ $row['warehouse_code'] }}
                                -
                                {{ $row['warehouse_name'] }}
                            </td>

                            <td>

                                <strong>
                                    {{ $row['item_code'] }}
                                </strong>

                                <br>

                                <small class="text-muted">
                                    {{ $row['item_name'] }}
                                </small>

                            </td>

                            <td class="text-right">

                                {{ number_format(
                                    $row['qty_on_hand'],
                                    2,
                                    ',',
                                    '.'
                                ) }}

                            </td>

                            <td class="text-right">

                                Rp {{ number_format(
                                    $row['average_cost'],
                                    2,
                                    ',',
                                    '.'
                                ) }}

                            </td>

                            <td class="text-right">

                                <strong>
                                    Rp {{ number_format(
                                        $row['inventory_value'],
                                        2,
                                        ',',
                                        '.'
                                    ) }}
                                </strong>

                            </td>

                            <td>

                                @if(
                                    $row[
                                        'last_transaction_date'
                                    ]
                                )

                                    {{ \Illuminate\Support\Carbon::parse(
                                        $row[
                                            'last_transaction_date'
                                        ]
                                    )->format('d-m-Y') }}

                                @else
                                    -
                                @endif

                            </td>

                            <td>

                                @can('inventory.stock-ledger.view')

                                    <a
                                        href="{{ route(
                                            'erp.inventory.stock-ledger.index',
                                            [
                                                'warehouse_id' =>
                                                    $row['warehouse_id'],

                                                'item_id' =>
                                                    $row['item_id'],

                                                'date_to' =>
                                                    request(
                                                        'as_of_date'
                                                    ),
                                            ]
                                        ) }}"
                                        class="btn btn-sm btn-info"
                                    >
                                        <i class="fas fa-list-alt mr-1"></i>
                                        Ledger
                                    </a>

                                @else

                                    <span class="text-muted">
                                        -
                                    </span>

                                @endcan

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="7"
                                class="text-center"
                            >
                                Tidak ada data inventory valuation.
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

        $('#inventory-valuation-table').DataTable({
            responsive: true,
            autoWidth: false,
            pageLength: 25,
            order: [],
        });

    });
</script>

@stop