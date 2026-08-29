@extends('adminlte::page')

@section('title', 'Inventory Transfer')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0">
                Inventory Transfer
            </h1>
        </div>

        @can('inventory.transfer.create')
            <a
                href="{{ route('erp.inventory.transfer.create') }}"
                class="btn btn-primary"
            >
                <i class="fas fa-plus mr-1"></i>
                New Transfer
            </a>
        @endcan
    </div>
@stop

@section('content')

    {{-- ============================================================
        FILTER
    ============================================================ --}}

    <div class="card card-outline card-primary">

        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-filter mr-1"></i>
                Filter
            </h3>
        </div>

        <div class="card-body">

            <form
                method="GET"
                action="{{ route('erp.inventory.transfer.index') }}"
            >

                <div class="row">

                    {{-- STATUS --}}
                    <div class="col-md-3">

                        <div class="form-group">

                            <label>
                                Status
                            </label>

                            <select
                                name="status"
                                class="form-control"
                            >

                                <option value="">
                                    Semua Status
                                </option>

                                <option
                                    value="DRAFT"
                                    @selected(request('status') === 'DRAFT')
                                >
                                    DRAFT
                                </option>

                                <option
                                    value="POSTED"
                                    @selected(request('status') === 'POSTED')
                                >
                                    POSTED
                                </option>

                            </select>

                        </div>

                    </div>

                    {{-- SOURCE WAREHOUSE --}}
                    <div class="col-md-4">

                        <div class="form-group">

                            <label>
                                Source Warehouse
                            </label>

                            <select
                                name="source_warehouse_id"
                                class="form-control"
                            >

                                <option value="">
                                    Semua Gudang
                                </option>

                                @foreach($warehouses as $warehouse)

                                    <option
                                        value="{{ $warehouse->id }}"
                                        @selected(
                                            (string) request(
                                                'source_warehouse_id'
                                            )
                                            ===
                                            (string) $warehouse->id
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

                    {{-- DESTINATION WAREHOUSE --}}
                    <div class="col-md-4">

                        <div class="form-group">

                            <label>
                                Destination Warehouse
                            </label>

                            <select
                                name="destination_warehouse_id"
                                class="form-control"
                            >

                                <option value="">
                                    Semua Gudang
                                </option>

                                @foreach($warehouses as $warehouse)

                                    <option
                                        value="{{ $warehouse->id }}"
                                        @selected(
                                            (string) request(
                                                'destination_warehouse_id'
                                            )
                                            ===
                                            (string) $warehouse->id
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

                    {{-- BUTTON --}}
                    <div class="col-md-1">

                        <div class="form-group">

                            <label>
                                &nbsp;
                            </label>

                            <div>

                                <button
                                    type="submit"
                                    class="btn btn-primary btn-block"
                                    title="Apply Filter"
                                >
                                    <i class="fas fa-search"></i>
                                </button>

                            </div>

                        </div>

                    </div>

                </div>

                <div class="row">

                    <div class="col-12">

                        <a
                            href="{{ route('erp.inventory.transfer.index') }}"
                            class="btn btn-default btn-sm"
                        >
                            <i class="fas fa-sync-alt mr-1"></i>
                            Reset Filter
                        </a>

                    </div>

                </div>

            </form>

        </div>

    </div>

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
                ×
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
                ×
            </button>

            <i class="icon fas fa-ban"></i>

            {{ session('error') }}

        </div>

    @endif

    {{-- ============================================================
        TRANSFER TABLE
    ============================================================ --}}

    <div class="card">

        <div class="card-header">

            <h3 class="card-title">

                <i class="fas fa-exchange-alt mr-1"></i>

                Daftar Inventory Transfer

            </h3>

        </div>

        <div class="card-body table-responsive p-0">

            <table
                class="table table-hover table-striped"
            >

                <thead>

                    <tr>

                        <th style="width: 50px;">
                            #
                        </th>

                        <th>
                            Transfer No
                        </th>

                        <th>
                            Date
                        </th>

                        <th>
                            Source
                        </th>

                        <th>
                            Destination
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Created By
                        </th>

                        <th>
                            Posted By
                        </th>

                        <th
                            class="text-center"
                            style="width: 100px;"
                        >
                            Action
                        </th>

                    </tr>

                </thead>

                <tbody>

                    @forelse($transfers as $transfer)

                        <tr>

                            <td>
                                {{ $transfers->firstItem() + $loop->index }}
                            </td>

                            {{-- TRANSFER NO --}}
                            <td>

                                <a
                                    href="{{ route(
                                        'erp.inventory.transfer.show',
                                        $transfer
                                    ) }}"
                                >
                                    <strong>
                                        {{ $transfer->transfer_no }}
                                    </strong>
                                </a>

                            </td>

                            {{-- DATE --}}
                            <td>

                                {{ optional(
                                    $transfer->transfer_date
                                )->format('d-m-Y') }}

                            </td>

                            {{-- SOURCE --}}
                            <td>

                                @if($transfer->sourceWarehouse)

                                    <strong>
                                        {{ $transfer->sourceWarehouse->code }}
                                    </strong>

                                    <br>

                                    <small class="text-muted">
                                        {{ $transfer->sourceWarehouse->name }}
                                    </small>

                                @else

                                    <span class="text-muted">
                                        -
                                    </span>

                                @endif

                            </td>

                            {{-- DESTINATION --}}
                            <td>

                                @if($transfer->destinationWarehouse)

                                    <strong>
                                        {{ $transfer->destinationWarehouse->code }}
                                    </strong>

                                    <br>

                                    <small class="text-muted">
                                        {{ $transfer->destinationWarehouse->name }}
                                    </small>

                                @else

                                    <span class="text-muted">
                                        -
                                    </span>

                                @endif

                            </td>

                            {{-- STATUS --}}
                            <td>

                                @if($transfer->status === 'POSTED')

                                    <span class="badge badge-success">
                                        POSTED
                                    </span>

                                @else

                                    <span class="badge badge-warning">
                                        DRAFT
                                    </span>

                                @endif

                            </td>

                            {{-- CREATED BY --}}
                            <td>

                                {{ $transfer->creator?->name ?? '-' }}

                            </td>

                            {{-- POSTED BY --}}
                            <td>

                                @if($transfer->poster)

                                    {{ $transfer->poster->name }}

                                    @if($transfer->posted_at)

                                        <br>

                                        <small class="text-muted">

                                            {{ $transfer
                                                ->posted_at
                                                ->format(
                                                    'd-m-Y H:i'
                                                )
                                            }}

                                        </small>

                                    @endif

                                @else

                                    <span class="text-muted">
                                        -
                                    </span>

                                @endif

                            </td>

                            {{-- ACTION --}}
                            <td class="text-center">

                                @can('inventory.transfer.view')

                                    <a
                                        href="{{ route(
                                            'erp.inventory.transfer.show',
                                            $transfer
                                        ) }}"
                                        class="btn btn-sm btn-info"
                                        title="View"
                                    >
                                        <i class="fas fa-eye"></i>
                                    </a>

                                @endcan

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="9"
                                class="text-center text-muted py-4"
                            >

                                <i class="fas fa-inbox fa-2x mb-2"></i>

                                <br>

                                Belum ada inventory transfer.

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

        @if($transfers->hasPages())

            <div class="card-footer clearfix">

                <div class="float-right">

                    {{ $transfers->links() }}

                </div>

            </div>

        @endif

    </div>

@stop