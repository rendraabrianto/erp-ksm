@extends('adminlte::page')

@section('title', 'Preview Inventory Reconciliation')

@section('content_header')
    <h1>Preview Inventory Adjustment</h1>
@stop

@section('content')

    <div class="card">
        <div class="card-body">

            <strong>Warehouse:</strong>
            {{ $warehouse->code }} - {{ $warehouse->name }}
            <br>

            <strong>Item:</strong>
            {{ $item->code }} - {{ $item->name }}
            <br>

            <strong>Period:</strong>
            {{ $dateFrom }} s/d {{ $dateTo }}

        </div>
    </div>

    <div class="row">

        <div class="col-md-3">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>
                        {{ $preview['proposal_count'] }}
                    </h3>
                    <p>Total Proposal</p>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>
                        {{ $preview['recover_missing_count'] }}
                    </h3>
                    <p>Recovery</p>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="small-box bg-primary">
                <div class="inner">
                    <h3>
                        {{ $preview['correct_mismatch_count'] }}
                    </h3>
                    <p>Cost Correction</p>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="small-box bg-danger">
                <div class="inner">
                    <h3>
                        {{ $preview['reverse_orphan_count'] }}
                    </h3>
                    <p>Reversal</p>
                </div>
            </div>
        </div>

    </div>

    <div class="card">

        <div class="card-header">
            <h3 class="card-title">
                Proposed Journals
            </h3>
        </div>

        <div class="card-body">

            @forelse($preview['proposals'] as $proposal)

                <div class="card card-outline card-secondary">

                    <div class="card-header">

                        <strong>
                            {{ $proposal['action'] }}
                        </strong>

                        <span class="float-right">
                            {{ $proposal['reference_type'] }}
                            #{{ $proposal['reference_id'] }}
                        </span>

                    </div>

                    <div class="card-body p-0">

                        <table class="table table-sm">

                            <thead>
                                <tr>
                                    <th>Account</th>
                                    <th>Description</th>
                                    <th class="text-right">
                                        Debit
                                    </th>
                                    <th class="text-right">
                                        Credit
                                    </th>
                                </tr>
                            </thead>

                            <tbody>

                                @foreach(
                                    $proposal['lines']
                                    as $line
                                )
                                    <tr>
                                        <td>
                                            @php
                                                $account =
                                                    $accounts->get(
                                                        (int) $line['account_id']
                                                    );
                                            @endphp

                                            @if($account)
                                                {{ $account->code }}
                                                -
                                                {{ $account->name }}
                                            @else
                                                ID {{ $line['account_id'] }}
                                            @endif
                                        </td>

                                        <td>
                                            {{ $line['description'] }}
                                        </td>

                                        <td class="text-right">
                                            {{ number_format(
                                                $line['debit'],
                                                2,
                                                ',',
                                                '.'
                                            ) }}
                                        </td>

                                        <td class="text-right">
                                            {{ number_format(
                                                $line['credit'],
                                                2,
                                                ',',
                                                '.'
                                            ) }}
                                        </td>
                                    </tr>
                                @endforeach

                            </tbody>

                        </table>

                    </div>

                </div>

            @empty

                <div class="alert alert-success">
                    Tidak ada adjustment yang diperlukan.
                </div>

            @endforelse

        </div>

        <div class="card-footer">

            <a
                href="{{ route(
                    'erp.inventory.reconciliation.index'
                ) }}"
                class="btn btn-secondary"
            >
                Kembali
            </a>

            {{-- INI BAGIAN APPLY --}}
            @can('inventory.reconciliation.apply')

                @if($preview['proposal_count'] > 0)

                    <form
                        method="POST"
                        action="{{ route(
                            'erp.inventory.reconciliation.apply'
                        ) }}"
                        class="d-inline"
                        onsubmit="return confirm(
                            'Yakin ingin memposting seluruh jurnal reconciliation? Tindakan ini akan membuat jurnal accounting.'
                        );"
                    >
                        @csrf

                        <input
                            type="hidden"
                            name="warehouse_id"
                            value="{{ $warehouse->id }}"
                        >

                        <input
                            type="hidden"
                            name="item_id"
                            value="{{ $item->id }}"
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
                            class="btn btn-danger"
                        >
                            <i class="fas fa-check"></i>
                            Apply Reconciliation
                        </button>

                    </form>

                @endif

            @endcan

        </div>

    </div>

@stop