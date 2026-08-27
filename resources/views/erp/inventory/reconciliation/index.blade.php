@extends('adminlte::page')

@section('title', 'Inventory Reconciliation')

@section('content_header')
    <h1>Inventory Reconciliation</h1>
@stop

@section('content')

    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card card-primary">
        <div class="card-header">
            <h3 class="card-title">
                Filter Reconciliation
            </h3>
        </div>

        <form
            method="POST"
            action="{{ route('erp.inventory.reconciliation.run') }}"
        >
            @csrf

            <div class="card-body">

                <div class="row">

                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Warehouse</label>

                            <select
                                name="warehouse_id"
                                class="form-control"
                                required
                            >
                                <option value="">
                                    -- Pilih Warehouse --
                                </option>

                                @foreach($warehouses as $warehouse)
                                    <option
                                        value="{{ $warehouse->id }}"
                                        @selected(
                                            old(
                                                'warehouse_id',
                                                $selectedWarehouseId ?? null
                                            )
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
                            <label>Item</label>

                            <select
                                name="item_id"
                                class="form-control"
                                required
                            >
                                <option value="">
                                    -- Pilih Item --
                                </option>

                                @foreach($items as $item)
                                    <option
                                        value="{{ $item->id }}"
                                        @selected(
                                            old(
                                                'item_id',
                                                $selectedItemId ?? null
                                            )
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
                            <label>Date From</label>

                            <input
                                type="date"
                                name="date_from"
                                class="form-control"
                                value="{{ old(
                                    'date_from',
                                    $dateFrom ?? ''
                                ) }}"
                                required
                            >
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Date To</label>

                            <input
                                type="date"
                                name="date_to"
                                class="form-control"
                                value="{{ old(
                                    'date_to',
                                    $dateTo ?? ''
                                ) }}"
                                required
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
                    <i class="fas fa-search"></i>
                    Run Reconciliation
                </button>
            </div>
        </form>
    </div>

    @isset($result)

        <div class="row">

            <div class="col-md-3">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3>
                            {{ number_format(
                                $result['final_qty'],
                                2
                            ) }}
                        </h3>
                        <p>Final Qty</p>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3>
                            Rp {{ number_format(
                                $result['final_value'],
                                2,
                                ',',
                                '.'
                            ) }}
                        </h3>
                        <p>Inventory Value</p>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="small-box bg-primary">
                    <div class="inner">
                        <h3>
                            Rp {{ number_format(
                                $result['final_average_cost'],
                                2,
                                ',',
                                '.'
                            ) }}
                        </h3>
                        <p>Average Cost</p>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div
                    class="small-box
                    {{ $result['is_reconciled']
                        ? 'bg-success'
                        : 'bg-danger' }}"
                >
                    <div class="inner">
                        <h3>
                            {{ $result['is_reconciled']
                                ? 'YES'
                                : 'NO' }}
                        </h3>
                        <p>Reconciled</p>
                    </div>
                </div>
            </div>

        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    Reconciliation Summary
                </h3>
            </div>

            <div class="card-body">

                {{-- <div class="row text-center">

                    <div class="col-md-4">
                        <h4>
                            {{ $result['missing_journal_count'] }}
                        </h4>
                        <span>Missing Journal</span>
                    </div>

                    <div class="col-md-4">
                        <h4>
                            {{ $result['cost_mismatch_count'] }}
                        </h4>
                        <span>Cost Mismatch</span>
                    </div>

                    <div class="col-md-4">
                        <h4>
                            {{ $result['orphan_journal_count'] }}
                        </h4>
                        <span>Orphan Journal</span>
                    </div>

                </div> --}}

                <div class="row">

                    <div class="col-md-4">
                        <div class="info-box">
                            <span class="info-box-icon bg-danger">
                                <i class="fas fa-file-excel"></i>
                            </span>

                            <div class="info-box-content">
                                <span class="info-box-text">
                                    Missing Journal
                                </span>

                                <span class="info-box-number">
                                    {{ $result['missing_journal_count'] }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="info-box">
                            <span class="info-box-icon bg-warning">
                                <i class="fas fa-calculator"></i>
                            </span>

                            <div class="info-box-content">
                                <span class="info-box-text">
                                    Cost Mismatch
                                </span>

                                <span class="info-box-number">
                                    {{ $result['cost_mismatch_count'] }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="info-box">
                            <span class="info-box-icon bg-secondary">
                                <i class="fas fa-unlink"></i>
                            </span>

                            <div class="info-box-content">
                                <span class="info-box-text">
                                    Orphan Journal
                                </span>

                                <span class="info-box-number">
                                    {{ $result['orphan_journal_count'] }}
                                </span>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </div>

        @if(! $result['is_reconciled'])
            @can('inventory.reconciliation.preview')
                <form
                    method="POST"
                    action="{{ route(
                        'erp.inventory.reconciliation.preview'
                    ) }}"
                >
                    @csrf

                    <input
                        type="hidden"
                        name="warehouse_id"
                        value="{{ $selectedWarehouseId }}"
                    >

                    <input
                        type="hidden"
                        name="item_id"
                        value="{{ $selectedItemId }}"
                    >

                    <input
                        type="hidden"
                        name="date_from"
                        value="{{ $dateFrom }}"
                    >

                    <input
                        type="hidden"
                        name="date_to"
                        value="{{ $dateTo }}"
                    >

                    <button
                        type="submit"
                        class="btn btn-warning mb-3"
                    >
                        <i class="fas fa-eye"></i>
                        Preview Adjustment
                    </button>
                </form>
            @endcan
        @endif

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    Problem Transactions
                </h3>
            </div>

            @isset($result)

                <div class="card mt-3">
                    <div class="card-header">
                        <h3 class="card-title">
                            Orphan Journals
                        </h3>
                    </div>

                    <div class="card-body table-responsive p-0">

                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Journal No</th>
                                    <th>Date</th>
                                    <th>Reference</th>
                                    <th>Purpose</th>
                                    <th class="text-right">
                                        Inventory Amount
                                    </th>
                                    <th>Status</th>
                                </tr>
                            </thead>

                            <tbody>

                                @forelse(
                                    $result['orphan_journals']
                                    as $journal
                                )

                                    <tr>

                                        <td>
                                            {{ $journal['journal_no'] }}
                                        </td>

                                        <td>
                                            {{ \Illuminate\Support\Carbon::parse(
                                                $journal['journal_date']
                                            )->format('d-m-Y') }}
                                        </td>

                                        <td>
                                            {{ $journal['reference_type'] }}
                                            #{{ $journal['reference_id'] }}
                                        </td>

                                        <td>
                                            {{ $journal['journal_purpose'] ?? 'NORMAL' }}
                                        </td>

                                        <td class="text-right">
                                            Rp {{ number_format(
                                                $journal['inventory_amount'],
                                                2,
                                                ',',
                                                '.'
                                            ) }}
                                        </td>

                                        <td>
                                            <span class="badge badge-danger">
                                                {{ $journal['status'] }}
                                            </span>
                                        </td>

                                    </tr>

                                @empty

                                    <tr>
                                        <td
                                            colspan="6"
                                            class="text-center"
                                        >
                                            Tidak ada orphan journal.
                                        </td>
                                    </tr>

                                @endforelse

                            </tbody>
                        </table>

                    </div>
                </div>

            @endisset

            <div class="card-body table-responsive p-0">

                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Reference</th>
                            <th>Qty In</th>
                            <th>Qty Out</th>
                            <th>Expected Cost</th>
                            <th>Journal</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse(
                            collect($result['rows'])
                                ->where(
                                    'status',
                                    '!=',
                                    'MATCH'
                                )
                            as $row
                        )
                            <tr>
                                <td>
                                    {{ \Illuminate\Support\Carbon::parse(
                                        $row['transaction_date']
                                    )->format('d-m-Y') }}
                                </td>

                                <td>
                                    {{ $row['reference_type'] }}
                                    #{{ $row['reference_id'] }}
                                </td>

                                <td>
                                    {{ number_format(
                                        $row['qty_in'],
                                        2
                                    ) }}
                                </td>

                                <td>
                                    {{ number_format(
                                        $row['qty_out'],
                                        2
                                    ) }}
                                </td>

                                <td>
                                    Rp {{ number_format(
                                        $row['expected_total_cost'],
                                        2,
                                        ',',
                                        '.'
                                    ) }}
                                </td>

                                <td>
                                    @if(
                                        $row['journal_amount']
                                        === null
                                    )
                                        -
                                    @else
                                        Rp {{ number_format(
                                            $row['journal_amount'],
                                            2,
                                            ',',
                                            '.'
                                        ) }}
                                    @endif
                                </td>

                                <td>
                                    <span class="badge badge-danger">
                                        {{ $row['status'] }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="7"
                                    class="text-center"
                                >
                                    Tidak ada transaksi bermasalah.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

            </div>
        </div>

    @endisset

@stop