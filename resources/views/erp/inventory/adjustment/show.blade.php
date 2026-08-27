@extends('adminlte::page')

@section('title', 'Stock Adjustment Detail')

@section('content_header')

    <div class="d-flex justify-content-between align-items-center">

        <h1>
            Stock Adjustment
            {{ $adjustment->adjustment_no }}
        </h1>

        <a
            href="{{ route(
                'erp.inventory.adjustment.index'
            ) }}"
            class="btn btn-secondary"
        >
            <i class="fas fa-arrow-left mr-1"></i>
            Back
        </a>

    </div>

@stop

@section('content')

    {{-- Success Message --}}
    @if(session('success'))

        <div class="alert alert-success">

            <i class="fas fa-check-circle mr-1"></i>

            {{ session('success') }}

        </div>

    @endif

    {{-- Error Message --}}
    @if(session('error'))

        <div class="alert alert-danger">

            <i class="fas fa-exclamation-triangle mr-1"></i>

            {{ session('error') }}

        </div>

    @endif


    {{-- ========================================================= --}}
    {{-- ADJUSTMENT INFORMATION --}}
    {{-- ========================================================= --}}

    <div class="card">

        <div class="card-header">

            <h3 class="card-title">
                Adjustment Information
            </h3>

            <div class="card-tools">

                @if(
                    $adjustment->status
                    ===
                    'POSTED'
                )

                    <span class="badge badge-success">
                        POSTED
                    </span>

                @else

                    <span class="badge badge-warning">
                        DRAFT
                    </span>

                @endif

            </div>

        </div>

        <div class="card-body">

            <div class="row">

                {{-- Adjustment No --}}
                <div class="col-md-4">

                    <strong>
                        Adjustment No
                    </strong>

                    <p>
                        {{ $adjustment->adjustment_no }}
                    </p>

                </div>

                {{-- Date --}}
                <div class="col-md-4">

                    <strong>
                        Adjustment Date
                    </strong>

                    <p>
                        {{ $adjustment
                            ->adjustment_date
                            ->format('d-m-Y') }}
                    </p>

                </div>

                {{-- Warehouse --}}
                <div class="col-md-4">

                    <strong>
                        Warehouse
                    </strong>

                    <p>
                        {{ $adjustment->warehouse?->code }}
                        -
                        {{ $adjustment->warehouse?->name }}
                    </p>

                </div>

                {{-- Reason --}}
                <div class="col-md-4">

                    <strong>
                        Reason
                    </strong>

                    <p>
                        {{ $adjustment->reason }}
                    </p>

                </div>

                {{-- Created By --}}
                <div class="col-md-4">

                    <strong>
                        Created By
                    </strong>

                    <p>
                        {{ $adjustment->creator?->name ?? '-' }}
                    </p>

                </div>

                {{-- Posted By --}}
                <div class="col-md-4">

                    <strong>
                        Posted By
                    </strong>

                    <p>
                        {{ $adjustment->poster?->name ?? '-' }}
                    </p>

                </div>

                {{-- Posted At --}}
                <div class="col-md-4">

                    <strong>
                        Posted At
                    </strong>

                    <p>

                        @if($adjustment->posted_at)

                            {{ $adjustment
                                ->posted_at
                                ->format(
                                    'd-m-Y H:i:s'
                                ) }}

                        @else

                            -

                        @endif

                    </p>

                </div>

                {{-- Status --}}
                <div class="col-md-4">

                    <strong>
                        Status
                    </strong>

                    <p>

                        @if(
                            $adjustment->status
                            ===
                            'POSTED'
                        )

                            <span class="badge badge-success">
                                POSTED
                            </span>

                        @else

                            <span class="badge badge-warning">
                                DRAFT
                            </span>

                        @endif

                    </p>

                </div>

            </div>


            {{-- Remarks --}}
            @if($adjustment->remarks)

                <hr>

                <strong>
                    Remarks
                </strong>

                <p class="mb-0">
                    {{ $adjustment->remarks }}
                </p>

            @endif

        </div>

    </div>


    {{-- ========================================================= --}}
    {{-- TRACEABILITY ACTIONS --}}
    {{-- ========================================================= --}}

    @if(
        $adjustment->status
        ===
        'POSTED'
    )

        <div class="card card-outline card-info">

            <div class="card-header">
                <h3 class="card-title">
                    Traceability
                </h3>
            </div>

            <div class="card-body">

                <div class="d-flex flex-wrap align-items-center">

                    {{-- JOURNAL --}}
                    @if(
                        isset($journal)
                        &&
                        $journal
                        &&
                        auth()->user()->can(
                            'accounting.journal.view'
                        )
                    )

                        <a
                            href="{{ route(
                                'erp.accounting.journals.show',
                                $journal
                            ) }}"
                            class="btn btn-secondary mr-2 mb-2"
                        >
                            <i class="fas fa-file-invoice mr-1"></i>

                            Journal
                            {{ $journal->journal_no }}
                        </a>

                    @elseif(
                        $adjustment->status === 'POSTED'
                    )

                        <span class="text-muted mr-2 mb-2">
                            <i class="fas fa-exclamation-circle mr-1"></i>
                            Journal tidak ditemukan
                        </span>

                    @endif

                </div>

                <small class="text-muted">
                    Gunakan tombol Journal untuk melihat jurnal accounting
                    dan tombol Ledger pada masing-masing item untuk melihat
                    transaksi Stock Ledger adjustment.
                </small>

            </div>

        </div>

    @endif


    {{-- ========================================================= --}}
    {{-- ADJUSTMENT DETAILS --}}
    {{-- ========================================================= --}}

    <div class="card">

        <div class="card-header">

            <h3 class="card-title">
                Adjustment Details
            </h3>

        </div>

        <div class="card-body table-responsive">

            <table
                class="table table-bordered table-hover"
            >

                <thead>

                    <tr>

                        <th>
                            Item
                        </th>

                        <th class="text-right">
                            System Qty
                        </th>

                        <th class="text-right">
                            Physical Qty
                        </th>

                        <th class="text-right">
                            Adjustment Qty
                        </th>

                        <th class="text-right">
                            Unit Cost
                        </th>

                        <th class="text-right">
                            Total Cost
                        </th>

                        <th>
                            Remarks
                        </th>

                        <th style="width:130px;">
                            Action
                        </th>

                    </tr>

                </thead>

                <tbody>

                    @forelse($adjustment->details as $detail)

                        <tr>

                            <td>

                                <strong>
                                    {{ $detail->item?->code }}
                                </strong>

                                <br>

                                <small class="text-muted">
                                    {{ $detail->item?->name }}
                                </small>

                            </td>

                            <td class="text-right">

                                {{ number_format(
                                    $detail->system_qty,
                                    4,
                                    ',',
                                    '.'
                                ) }}

                            </td>

                            <td class="text-right">

                                {{ number_format(
                                    $detail->physical_qty,
                                    4,
                                    ',',
                                    '.'
                                ) }}

                            </td>

                            <td class="text-right">

                                @if($detail->adjustment_qty > 0)

                                    <span class="text-success font-weight-bold">
                                        +{{ number_format(
                                            $detail->adjustment_qty,
                                            4,
                                            ',',
                                            '.'
                                        ) }}
                                    </span>

                                @elseif($detail->adjustment_qty < 0)

                                    <span class="text-danger font-weight-bold">
                                        {{ number_format(
                                            $detail->adjustment_qty,
                                            4,
                                            ',',
                                            '.'
                                        ) }}
                                    </span>

                                @else

                                    <span class="text-muted">
                                        0,0000
                                    </span>

                                @endif

                            </td>

                            <td class="text-right">

                                Rp {{ number_format(
                                    $detail->unit_cost,
                                    2,
                                    ',',
                                    '.'
                                ) }}

                            </td>

                            <td class="text-right">

                                <strong>
                                    Rp {{ number_format(
                                        $detail->total_cost,
                                        2,
                                        ',',
                                        '.'
                                    ) }}
                                </strong>

                            </td>

                            <td>
                                {{ $detail->remarks ?? '-' }}
                            </td>

                            <td>

                                @can('inventory.stock-ledger.view')

                                    <a
                                        href="{{ route(
                                            'erp.inventory.stock-ledger.index',
                                            [
                                                'warehouse_id' =>
                                                    $adjustment->warehouse_id,

                                                'item_id' =>
                                                    $detail->item_id,

                                                'date_to' =>
                                                    $adjustment
                                                        ->adjustment_date
                                                        ->format('Y-m-d'),
                                            ]
                                        ) }}"
                                        class="btn btn-info btn-sm"
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
                                colspan="8"
                                class="text-center"
                            >
                                Tidak ada detail adjustment.
                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>


        {{-- ===================================================== --}}
        {{-- POST BUTTON --}}
        {{-- ===================================================== --}}

        @if(
            $adjustment->status
            ===
            'DRAFT'
            &&
            auth()->user()->can(
                'inventory.adjustment.post'
            )
        )

            <div class="card-footer">

                <div
                    class="
                        d-flex
                        justify-content-between
                        align-items-center
                    "
                >

                    <div>

                        <span class="text-muted">

                            <i
                                class="
                                    fas
                                    fa-info-circle
                                    mr-1
                                "
                            ></i>

                            Saat POST,
                            System Qty dan costing akan
                            dihitung ulang berdasarkan
                            posisi inventory terbaru.

                        </span>

                    </div>

                    <div>

                        <form
                            method="POST"
                            action="{{ route(
                                'erp.inventory.adjustment.post',
                                $adjustment
                            ) }}"
                            onsubmit="
                                return confirm(
                                    'Posting Stock Adjustment? '
                                    + 'System Qty dan costing '
                                    + 'akan dihitung ulang. '
                                    + 'Pastikan physical quantity '
                                    + 'sudah benar.'
                                );
                            "
                        >

                            @csrf

                            <button
                                type="submit"
                                class="btn btn-success"
                            >
                                <i
                                    class="
                                        fas
                                        fa-check
                                        mr-1
                                    "
                                ></i>

                                POST Adjustment
                            </button>

                        </form>

                    </div>

                </div>

            </div>

        @endif

    </div>

@stop