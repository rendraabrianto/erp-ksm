@extends('adminlte::page')

@section('title', 'Journal Detail')

@section('content_header')

    <div class="d-flex justify-content-between align-items-center">

        <h1>
            Journal {{ $journal->journal_no }}
        </h1>

        <button
            type="button"
            class="btn btn-secondary"
            onclick="history.back()"
        >
            <i class="fas fa-arrow-left mr-1"></i>
            Back
        </button>

    </div>

@stop

@section('content')

    {{-- ========================================================= --}}
    {{-- JOURNAL INFORMATION --}}
    {{-- ========================================================= --}}

    <div class="card">

        <div class="card-header">

            <h3 class="card-title">
                Journal Information
            </h3>

        </div>

        <div class="card-body">

            <div class="row">

                {{-- Journal No --}}
                <div class="col-md-4">

                    <strong>
                        Journal No
                    </strong>

                    <p>
                        {{ $journal->journal_no }}
                    </p>

                </div>

                {{-- Journal Date --}}
                <div class="col-md-4">

                    <strong>
                        Journal Date
                    </strong>

                    <p>
                        {{ \Illuminate\Support\Carbon::parse(
                            $journal->journal_date
                        )->format('d-m-Y') }}
                    </p>

                </div>

                {{-- Purpose --}}
                <div class="col-md-4">

                    <strong>
                        Purpose
                    </strong>

                    <p>

                        @if(
                            $journal->journal_purpose
                            ===
                            'NORMAL'
                        )

                            <span class="badge badge-primary">
                                NORMAL
                            </span>

                        @elseif(
                            $journal->journal_purpose
                            ===
                            'REVERSAL'
                        )

                            <span class="badge badge-danger">
                                REVERSAL
                            </span>

                        @else

                            <span class="badge badge-secondary">
                                {{ $journal->journal_purpose }}
                            </span>

                        @endif

                    </p>

                </div>

                {{-- Reference Type --}}
                <div class="col-md-4">

                    <strong>
                        Reference Type
                    </strong>

                    <p>
                        {{ $journal->reference_type }}
                    </p>

                </div>

                {{-- Reference ID --}}
                <div class="col-md-4">

                    <strong>
                        Reference ID
                    </strong>

                    <p>
                        {{ $journal->reference_id }}
                    </p>

                </div>

                {{-- Created By --}}
                <div class="col-md-4">

                    <strong>
                        Created By
                    </strong>

                    <p>
                        {{ $journal->created_by }}
                    </p>

                </div>

            </div>

            <hr>

            <strong>
                Description
            </strong>

            <p class="mb-0">
                {{ $journal->description ?? '-' }}
            </p>

        </div>

    </div>


    {{-- ========================================================= --}}
    {{-- JOURNAL LINES --}}
    {{-- ========================================================= --}}

    <div class="card">

        <div class="card-header">

            <h3 class="card-title">
                Journal Lines
            </h3>

        </div>

        <div class="card-body table-responsive">

            <table
                class="table table-bordered table-hover"
            >

                <thead>

                    <tr>

                        <th>
                            Account
                        </th>

                        <th>
                            Description
                        </th>

                        <th class="text-right">
                            Qty
                        </th>

                        <th class="text-right">
                            Unit Price
                        </th>

                        <th class="text-right">
                            Debit
                        </th>

                        <th class="text-right">
                            Credit
                        </th>

                    </tr>

                </thead>

                <tbody>

                    @forelse($journal->details as $detail)

                        <tr>

                            {{-- Account --}}
                            <td>

                                <strong>
                                    {{ $detail->account?->code }}
                                </strong>

                                -

                                {{ $detail->account?->name }}

                            </td>

                            {{-- Description --}}
                            <td>

                                {{ $detail->description ?? '-' }}

                            </td>

                            {{-- Quantity --}}
                            <td class="text-right">

                                {{ number_format(
                                    $detail->quantity,
                                    4,
                                    ',',
                                    '.'
                                ) }}

                            </td>

                            {{-- Unit Price --}}
                            <td class="text-right">

                                Rp
                                {{ number_format(
                                    $detail->unit_price,
                                    2,
                                    ',',
                                    '.'
                                ) }}

                            </td>

                            {{-- Debit --}}
                            <td class="text-right">

                                @if(
                                    (float) $detail->debit
                                    >
                                    0
                                )

                                    <strong>
                                        Rp
                                        {{ number_format(
                                            $detail->debit,
                                            2,
                                            ',',
                                            '.'
                                        ) }}
                                    </strong>

                                @else

                                    Rp 0,00

                                @endif

                            </td>

                            {{-- Credit --}}
                            <td class="text-right">

                                @if(
                                    (float) $detail->credit
                                    >
                                    0
                                )

                                    <strong>
                                        Rp
                                        {{ number_format(
                                            $detail->credit,
                                            2,
                                            ',',
                                            '.'
                                        ) }}
                                    </strong>

                                @else

                                    Rp 0,00

                                @endif

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="6"
                                class="text-center"
                            >
                                Tidak ada journal lines.
                            </td>

                        </tr>

                    @endforelse

                </tbody>

                {{-- TOTAL --}}
                <tfoot>

                    @php
                        $totalDebit =
                            (float)
                            $journal->details
                                ->sum('debit');

                        $totalCredit =
                            (float)
                            $journal->details
                                ->sum('credit');

                        $balanced =
                            round(
                                $totalDebit,
                                2
                            )
                            ===
                            round(
                                $totalCredit,
                                2
                            );
                    @endphp

                    <tr>

                        <th
                            colspan="4"
                            class="text-right"
                        >
                            TOTAL
                        </th>

                        <th class="text-right">

                            Rp
                            {{ number_format(
                                $totalDebit,
                                2,
                                ',',
                                '.'
                            ) }}

                        </th>

                        <th class="text-right">

                            Rp
                            {{ number_format(
                                $totalCredit,
                                2,
                                ',',
                                '.'
                            ) }}

                        </th>

                    </tr>

                    <tr>

                        <th
                            colspan="4"
                            class="text-right"
                        >
                            Balance Status
                        </th>

                        <th
                            colspan="2"
                            class="text-center"
                        >

                            @if($balanced)

                                <span class="badge badge-success">
                                    BALANCED
                                </span>

                            @else

                                <span class="badge badge-danger">
                                    NOT BALANCED
                                </span>

                            @endif

                        </th>

                    </tr>

                </tfoot>

            </table>

        </div>

    </div>

@stop