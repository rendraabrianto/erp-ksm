@extends('adminlte::page')

@section('title', 'Stock Adjustment')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>Stock Adjustment</h1>

        @can('inventory.adjustment.create')
            <a
                href="{{ route('erp.inventory.adjustment.create') }}"
                class="btn btn-primary"
            >
                <i class="fas fa-plus mr-1"></i>
                New Adjustment
            </a>
        @endcan
    </div>
@stop

@section('content')

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <div class="card card-primary">

        <div class="card-header">
            <h3 class="card-title">
                Filter
            </h3>
        </div>

        <form
            method="GET"
            action="{{ route('erp.inventory.adjustment.index') }}"
        >

            <div class="card-body">

                <div class="row">

                    <div class="col-md-6">

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

                    <div class="col-md-6">

                        <div class="form-group">

                            <label>Status</label>

                            <select
                                name="status"
                                class="form-control"
                            >

                                <option value="">
                                    -- Semua Status --
                                </option>

                                <option
                                    value="DRAFT"
                                    @selected(
                                        request('status')
                                        === 'DRAFT'
                                    )
                                >
                                    DRAFT
                                </option>

                                <option
                                    value="POSTED"
                                    @selected(
                                        request('status')
                                        === 'POSTED'
                                    )
                                >
                                    POSTED
                                </option>

                            </select>

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
                        'erp.inventory.adjustment.index'
                    ) }}"
                    class="btn btn-secondary"
                >
                    Reset
                </a>

            </div>

        </form>

    </div>

    <div class="card">

        <div class="card-header">
            <h3 class="card-title">
                Adjustment List
            </h3>
        </div>

        <div class="card-body table-responsive">

            <table
                class="table table-bordered table-hover"
            >

                <thead>
                    <tr>
                        <th>No</th>
                        <th>Date</th>
                        <th>Warehouse</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th>Posted By</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse($adjustments as $adjustment)

                        <tr>

                            <td>
                                {{ $adjustment->adjustment_no }}
                            </td>

                            <td>
                                {{ $adjustment
                                    ->adjustment_date
                                    ->format('d-m-Y') }}
                            </td>

                            <td>
                                {{ $adjustment->warehouse?->code }}
                                -
                                {{ $adjustment->warehouse?->name }}
                            </td>

                            <td>
                                {{ $adjustment->reason }}
                            </td>

                            <td>

                                @if(
                                    $adjustment->status
                                    === 'POSTED'
                                )

                                    <span class="badge badge-success">
                                        POSTED
                                    </span>

                                @else

                                    <span class="badge badge-warning">
                                        DRAFT
                                    </span>

                                @endif

                            </td>

                            <td>
                                {{ $adjustment->creator?->name ?? '-' }}
                            </td>

                            <td>
                                {{ $adjustment->poster?->name ?? '-' }}
                            </td>

                            <td>

                                <a
                                    href="{{ route(
                                        'erp.inventory.adjustment.show',
                                        $adjustment
                                    ) }}"
                                    class="btn btn-sm btn-info"
                                >
                                    <i class="fas fa-eye"></i>
                                    View
                                </a>

                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td
                                colspan="8"
                                class="text-center"
                            >
                                Tidak ada data inventory adjustment.
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

        @if($adjustments->hasPages())

            <div class="card-footer">
                {{ $adjustments->links() }}
            </div>

        @endif

    </div>

@stop