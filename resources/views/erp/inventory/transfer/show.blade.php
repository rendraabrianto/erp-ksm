@extends('adminlte::page')

@section('title', 'Inventory Transfer - ' . $transfer->transfer_no)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0">
                Inventory Transfer
            </h1>

            <small class="text-muted">
                {{ $transfer->transfer_no }}
            </small>
        </div>

        <a
            href="{{ route('erp.inventory.transfer.index') }}"
            class="btn btn-default"
        >
            <i class="fas fa-arrow-left mr-1"></i>
            Back
        </a>
    </div>
@stop


@section('content')

    {{-- ============================================================
        FLASH MESSAGE
    ============================================================ --}}

    @if(session('success'))
        <div class="alert alert-success alert-dismissible">
            <button
                type="button"
                class="close"
                data-dismiss="alert"
            >
                &times;
            </button>

            <i class="icon fas fa-check"></i>
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible">
            <button
                type="button"
                class="close"
                data-dismiss="alert"
            >
                &times;
            </button>

            <i class="icon fas fa-ban"></i>
            {{ session('error') }}
        </div>
    @endif


    {{-- ============================================================
        DOCUMENT SUMMARY
    ============================================================ --}}

    <div class="row">

        <div class="col-md-3">
            <div class="info-box">

                <span class="info-box-icon bg-info">
                    <i class="fas fa-file-alt"></i>
                </span>

                <div class="info-box-content">

                    <span class="info-box-text">
                        Transfer No
                    </span>

                    <span class="info-box-number">
                        {{ $transfer->transfer_no }}
                    </span>

                </div>

            </div>
        </div>


        <div class="col-md-3">
            <div class="info-box">

                <span class="info-box-icon bg-secondary">
                    <i class="fas fa-calendar-alt"></i>
                </span>

                <div class="info-box-content">

                    <span class="info-box-text">
                        Transfer Date
                    </span>

                    <span class="info-box-number">
                        {{ optional($transfer->transfer_date)->format('d-m-Y') }}
                    </span>

                </div>

            </div>
        </div>


        <div class="col-md-3">
            <div class="info-box">

                <span class="info-box-icon bg-primary">
                    <i class="fas fa-boxes"></i>
                </span>

                <div class="info-box-content">

                    <span class="info-box-text">
                        Total Value
                    </span>

                    <span class="info-box-number">
                        Rp {{ number_format(
                            (float) $transfer->details->sum('total_cost'),
                            2,
                            ',',
                            '.'
                        ) }}
                    </span>

                </div>

            </div>
        </div>


        <div class="col-md-3">
            <div class="info-box">

                <span
                    class="info-box-icon
                        {{ $transfer->status === 'POSTED'
                            ? 'bg-success'
                            : 'bg-warning' }}"
                >
                    <i
                        class="fas
                            {{ $transfer->status === 'POSTED'
                                ? 'fa-check-circle'
                                : 'fa-clock' }}"
                    ></i>
                </span>

                <div class="info-box-content">

                    <span class="info-box-text">
                        Status
                    </span>

                    <span class="info-box-number">
                        {{ $transfer->status }}
                    </span>

                </div>

            </div>
        </div>

    </div>


    {{-- ============================================================
        TRANSFER HEADER
    ============================================================ --}}

    <div class="card card-outline card-primary">

        <div class="card-header">

            <h3 class="card-title">
                <i class="fas fa-exchange-alt mr-1"></i>
                Transfer Information
            </h3>

        </div>


        <div class="card-body">

            {{-- WAREHOUSE FLOW --}}

            <div class="row align-items-center">

                <div class="col-md-5">

                    <div class="callout callout-danger mb-0">

                        <small class="text-muted">
                            SOURCE WAREHOUSE
                        </small>

                        <h5 class="mb-1">

                            <i class="fas fa-warehouse mr-1"></i>

                            {{ $transfer->sourceWarehouse?->code ?? '-' }}

                        </h5>

                        <div>
                            {{ $transfer->sourceWarehouse?->name ?? '-' }}
                        </div>

                    </div>

                </div>


                <div class="col-md-2 text-center py-3">

                    <i
                        class="fas fa-long-arrow-alt-right
                               fa-3x text-primary"
                    ></i>

                    <div class="small text-muted mt-1">
                        TRANSFER
                    </div>

                </div>


                <div class="col-md-5">

                    <div class="callout callout-success mb-0">

                        <small class="text-muted">
                            DESTINATION WAREHOUSE
                        </small>

                        <h5 class="mb-1">

                            <i class="fas fa-warehouse mr-1"></i>

                            {{ $transfer->destinationWarehouse?->code ?? '-' }}

                        </h5>

                        <div>
                            {{ $transfer->destinationWarehouse?->name ?? '-' }}
                        </div>

                    </div>

                </div>

            </div>


            <hr>


            <div class="row">

                <div class="col-md-4">

                    <strong>
                        <i class="fas fa-user mr-1"></i>
                        Created By
                    </strong>

                    <p class="text-muted">
                        {{ $transfer->creator?->name ?? '-' }}
                    </p>

                </div>


                <div class="col-md-4">

                    <strong>
                        <i class="fas fa-user-check mr-1"></i>
                        Posted By
                    </strong>

                    <p class="text-muted">

                        @if($transfer->poster)

                            {{ $transfer->poster->name }}

                            @if($transfer->posted_at)
                                <br>

                                <small>
                                    {{ $transfer->posted_at->format(
                                        'd-m-Y H:i'
                                    ) }}
                                </small>
                            @endif

                        @else

                            Belum diposting

                        @endif

                    </p>

                </div>


                <div class="col-md-4">

                    <strong>
                        <i class="fas fa-comment-alt mr-1"></i>
                        Remarks
                    </strong>

                    <p class="text-muted">
                        {{ $transfer->remarks ?: '-' }}
                    </p>

                </div>

            </div>

        </div>

    </div>


    {{-- ============================================================
        TRANSFER ITEMS
    ============================================================ --}}

    <div class="card">

        <div class="card-header">

            <h3 class="card-title">

                <i class="fas fa-boxes mr-1"></i>

                Transfer Items

            </h3>


            <div class="card-tools">

                @if($transfer->status === 'DRAFT')

                    <span class="badge badge-warning">
                        Cost Snapshot
                    </span>

                @else

                    <span class="badge badge-success">
                        Authoritative Posted Cost
                    </span>

                @endif

            </div>

        </div>


        <div class="card-body table-responsive p-0">

            <table class="table table-bordered table-hover mb-0">

                <thead class="thead-light">

                    <tr>

                        <th style="width: 50px;">
                            #
                        </th>

                        <th>
                            Item
                        </th>

                        <th
                            class="text-right"
                            style="width: 130px;"
                        >
                            Qty
                        </th>

                        <th
                            class="text-right"
                            style="width: 160px;"
                        >
                            Unit Cost
                        </th>

                        <th
                            class="text-right"
                            style="width: 180px;"
                        >
                            Total Cost
                        </th>

                        <th>
                            Remarks
                        </th>

                    </tr>

                </thead>


                <tbody>

                    @foreach($transfer->details as $detail)

                        <tr>

                            <td class="text-center">
                                {{ $loop->iteration }}
                            </td>


                            <td>

                                <strong>
                                    {{ $detail->item?->code ?? '-' }}
                                </strong>

                                <br>

                                <small class="text-muted">
                                    {{ $detail->item?->name ?? '-' }}
                                </small>

                            </td>


                            <td class="text-right">

                                {{ number_format(
                                    (float) $detail->qty,
                                    4,
                                    ',',
                                    '.'
                                ) }}

                            </td>


                            <td class="text-right">

                                Rp {{ number_format(
                                    (float) $detail->unit_cost,
                                    2,
                                    ',',
                                    '.'
                                ) }}

                            </td>


                            <td class="text-right">

                                <strong>

                                    Rp {{ number_format(
                                        (float) $detail->total_cost,
                                        2,
                                        ',',
                                        '.'
                                    ) }}

                                </strong>

                            </td>


                            <td>
                                {{ $detail->remarks ?: '-' }}
                            </td>

                        </tr>

                    @endforeach

                </tbody>


                <tfoot>

                    <tr class="bg-light">

                        <th
                            colspan="4"
                            class="text-right"
                        >
                            TOTAL TRANSFER VALUE
                        </th>

                        <th class="text-right">

                            Rp {{ number_format(
                                (float) $transfer
                                    ->details
                                    ->sum('total_cost'),
                                2,
                                ',',
                                '.'
                            ) }}

                        </th>

                        <th></th>

                    </tr>

                </tfoot>

            </table>

        </div>

    </div>


    {{-- ============================================================
        STOCK LEDGER TRACEABILITY
    ============================================================ --}}

    <div class="card card-outline card-secondary">

        <div class="card-header">

            <h3 class="card-title">

                <i class="fas fa-route mr-1"></i>

                Stock Ledger Traceability

            </h3>

        </div>


        <div class="card-body table-responsive p-0">

            <table class="table table-bordered table-striped mb-0">

                <thead class="thead-light">

                    <tr>

                        <th style="width: 50px;">
                            #
                        </th>

                        <th>
                            Direction
                        </th>

                        <th>
                            Date
                        </th>

                        <th>
                            Warehouse
                        </th>

                        <th>
                            Item
                        </th>

                        <th class="text-right">
                            Qty In
                        </th>

                        <th class="text-right">
                            Qty Out
                        </th>

                        <th class="text-right">
                            Balance
                        </th>

                        <th class="text-right">
                            Unit Cost
                        </th>

                        <th class="text-right">
                            Value
                        </th>

                    </tr>

                </thead>


                <tbody>

                    @forelse($stockLedgers as $ledger)

                        @php
                            $isSource =
                                (int) $ledger->warehouse_id
                                ===
                                (int) $transfer->source_warehouse_id;
                        @endphp


                        <tr>

                            <td class="text-center">
                                {{ $loop->iteration }}
                            </td>


                            <td>

                                @if($isSource)

                                    <span class="badge badge-danger">

                                        <i class="fas fa-arrow-up mr-1"></i>

                                        SOURCE OUT

                                    </span>

                                @else

                                    <span class="badge badge-success">

                                        <i class="fas fa-arrow-down mr-1"></i>

                                        DESTINATION IN

                                    </span>

                                @endif

                            </td>


                            <td>

                                {{ optional(
                                    $ledger->transaction_date
                                )->format('d-m-Y') }}

                            </td>


                            <td>

                                <strong>
                                    {{ $ledger->warehouse?->code ?? '-' }}
                                </strong>

                                <br>

                                <small class="text-muted">
                                    {{ $ledger->warehouse?->name ?? '-' }}
                                </small>

                            </td>


                            <td>

                                <strong>
                                    {{ $ledger->item?->code ?? '-' }}
                                </strong>

                                <br>

                                <small class="text-muted">
                                    {{ $ledger->item?->name ?? '-' }}
                                </small>

                            </td>


                            <td class="text-right">

                                @if((float) $ledger->qty_in > 0)

                                    <span class="text-success font-weight-bold">

                                        {{ number_format(
                                            (float) $ledger->qty_in,
                                            4,
                                            ',',
                                            '.'
                                        ) }}

                                    </span>

                                @else
                                    -
                                @endif

                            </td>


                            <td class="text-right">

                                @if((float) $ledger->qty_out > 0)

                                    <span class="text-danger font-weight-bold">

                                        {{ number_format(
                                            (float) $ledger->qty_out,
                                            4,
                                            ',',
                                            '.'
                                        ) }}

                                    </span>

                                @else
                                    -
                                @endif

                            </td>


                            <td class="text-right">

                                {{ number_format(
                                    (float) $ledger->balance_qty,
                                    4,
                                    ',',
                                    '.'
                                ) }}

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

                                <strong>

                                    Rp {{ number_format(
                                        (float) $ledger->total_cost,
                                        2,
                                        ',',
                                        '.'
                                    ) }}

                                </strong>

                            </td>

                        </tr>


                    @empty

                        <tr>

                            <td
                                colspan="10"
                                class="text-center text-muted py-4"
                            >

                                <i
                                    class="fas fa-info-circle
                                           fa-2x mb-2"
                                ></i>

                                <br>

                                Transfer masih DRAFT.

                                <br>

                                Stock ledger akan dibuat saat transfer
                                diposting.

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>


    {{-- ============================================================
        ACTION
    ============================================================ --}}

    <div class="card">

        <div class="card-body">

            <div
                class="d-flex justify-content-between
                       align-items-center"
            >

                <a
                    href="{{ route(
                        'erp.inventory.transfer.index'
                    ) }}"
                    class="btn btn-default"
                >
                    <i class="fas fa-list mr-1"></i>
                    Back to Transfer List
                </a>


                @if($transfer->status === 'DRAFT')

                    @can('inventory.transfer.post')

                        <form
                            method="POST"
                            action="{{ route(
                                'erp.inventory.transfer.post',
                                $transfer
                            ) }}"
                            id="post-transfer-form"
                        >

                            @csrf

                            <button
                                type="submit"
                                class="btn btn-success"
                                id="post-transfer-button"
                            >

                                <i class="fas fa-check-circle mr-1"></i>

                                Post Inventory Transfer

                            </button>

                        </form>

                    @endcan


                @elseif($transfer->status === 'POSTED')

                    <div class="alert alert-success mb-0 py-2">

                        <i class="fas fa-check-circle mr-1"></i>

                        Inventory transfer sudah diposting.

                    </div>

                @endif

            </div>

        </div>

    </div>

@stop


@section('js')

<script>
(function () {

    const form =
        document.getElementById(
            'post-transfer-form'
        );

    if (!form) {
        return;
    }


    form.addEventListener(
        'submit',
        function (event) {

            const confirmed =
                confirm(
                    'Post inventory transfer ini?\n\n' +
                    'Source stock akan berkurang dan ' +
                    'destination stock akan bertambah.\n\n' +
                    'Dokumen yang sudah POSTED tidak dapat ' +
                    'diposting ulang.'
                );


            if (!confirmed) {

                event.preventDefault();

                return;

            }


            const button =
                document.getElementById(
                    'post-transfer-button'
                );


            button.disabled =
                true;


            button.innerHTML =
                '<i class="fas fa-spinner fa-spin mr-1"></i>' +
                ' Posting...';
        }
    );

})();
</script>

@stop