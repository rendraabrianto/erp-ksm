@extends('adminlte::page')

@section('title', 'Reconciliation History Detail')

@section('content_header')
    <h1>Reconciliation History Detail</h1>
@stop

@section('content')

<div class="card">

    <div class="card-header">
        <h3 class="card-title">
            Batch #{{ $history->id }}
        </h3>
    </div>

    <div class="card-body">

        <div class="row">

            <div class="col-md-6">

                <p>
                    <strong>Warehouse:</strong><br>
                    {{ $history->warehouse?->code }}
                    -
                    {{ $history->warehouse?->name }}
                </p>

                <p>
                    <strong>Item:</strong><br>
                    {{ $history->item?->code }}
                    -
                    {{ $history->item?->name }}
                </p>

                <p>
                    <strong>Period:</strong><br>
                    {{ $history->date_from->format('d-m-Y') }}
                    s/d
                    {{ $history->date_to->format('d-m-Y') }}
                </p>

            </div>

            <div class="col-md-6">

                <p>
                    <strong>Executed By:</strong><br>
                    {{ $history->executor?->name ?? '-' }}
                </p>

                <p>
                    <strong>Executed At:</strong><br>
                    {{ $history->executed_at?->format('d-m-Y H:i:s') }}
                </p>

                <p>
                    <strong>Status:</strong><br>

                    @if($history->is_reconciled_after)
                        <span class="badge badge-success">
                            RECONCILED
                        </span>
                    @else
                        <span class="badge badge-danger">
                            NOT RECONCILED
                        </span>
                    @endif
                </p>

            </div>

        </div>

    </div>

</div>

<div class="row">

    <div class="col-md-4">

        <div class="card card-outline card-danger">

            <div class="card-header">
                <h3 class="card-title">
                    Before
                </h3>
            </div>

            <div class="card-body">

                <p>
                    Missing:
                    <strong>
                        {{ $history->missing_before }}
                    </strong>
                </p>

                <p>
                    Mismatch:
                    <strong>
                        {{ $history->mismatch_before }}
                    </strong>
                </p>

                <p>
                    Orphan:
                    <strong>
                        {{ $history->orphan_before }}
                    </strong>
                </p>

            </div>

        </div>

    </div>

    <div class="col-md-4">

        <div class="card card-outline card-warning">

            <div class="card-header">
                <h3 class="card-title">
                    Posted
                </h3>
            </div>

            <div class="card-body">

                <p>
                    Recovery:
                    <strong>
                        {{ $history->recovery_posted }}
                    </strong>
                </p>

                <p>
                    Cost Correction:
                    <strong>
                        {{ $history->correction_posted }}
                    </strong>
                </p>

                <p>
                    Reversal:
                    <strong>
                        {{ $history->reversal_posted }}
                    </strong>
                </p>

                <p>
                    Total Journal:
                    <strong>
                        {{ $history->journal_posted_count }}
                    </strong>
                </p>

            </div>

        </div>

    </div>

    <div class="col-md-4">

        <div class="card card-outline card-success">

            <div class="card-header">
                <h3 class="card-title">
                    After
                </h3>
            </div>

            <div class="card-body">

                <p>
                    Missing:
                    <strong>
                        {{ $history->missing_after }}
                    </strong>
                </p>

                <p>
                    Mismatch:
                    <strong>
                        {{ $history->mismatch_after }}
                    </strong>
                </p>

                <p>
                    Orphan:
                    <strong>
                        {{ $history->orphan_after }}
                    </strong>
                </p>

            </div>

        </div>

    </div>

</div>

<a
    href="{{ route(
        'erp.inventory.reconciliation.history'
    ) }}"
    class="btn btn-secondary"
>
    Kembali
</a>

@stop