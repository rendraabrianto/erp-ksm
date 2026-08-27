@extends('adminlte::page')

@section('title', 'Reconciliation History')

@section('content_header')
    <h1>Inventory Reconciliation History</h1>
@stop

@section('content')

<div class="card">

    <div class="card-header">
        <h3 class="card-title">
            Reconciliation History
        </h3>
    </div>

    <div class="card-body table-responsive p-0">

        <table class="table table-bordered table-hover">

            <thead>
                <tr>
                    <th>Date</th>
                    <th>Warehouse</th>
                    <th>Item</th>
                    <th>Period</th>

                    <th class="text-center">
                        Before
                    </th>

                    <th class="text-center">
                        Posted
                    </th>

                    <th class="text-center">
                        After
                    </th>

                    <th>Status</th>
                    <th>Executed By</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>

                @forelse($histories as $history)

                    <tr>

                        <td>
                            {{ $history->executed_at
                                ? $history->executed_at->format('d-m-Y H:i')
                                : '-' }}
                        </td>

                        <td>
                            {{ $history->warehouse?->code }}
                            -
                            {{ $history->warehouse?->name }}
                        </td>

                        <td>
                            {{ $history->item?->code }}
                            -
                            {{ $history->item?->name }}
                        </td>

                        <td>
                            {{ $history->date_from->format('d-m-Y') }}
                            s/d
                            {{ $history->date_to->format('d-m-Y') }}
                        </td>

                        <td class="text-center">
                            M: {{ $history->missing_before }}
                            /
                            C: {{ $history->mismatch_before }}
                            /
                            O: {{ $history->orphan_before }}
                        </td>

                        <td class="text-center">
                            {{ $history->journal_posted_count }}
                        </td>

                        <td class="text-center">
                            M: {{ $history->missing_after }}
                            /
                            C: {{ $history->mismatch_after }}
                            /
                            O: {{ $history->orphan_after }}
                        </td>

                        <td>
                            @if($history->is_reconciled_after)
                                <span class="badge badge-success">
                                    RECONCILED
                                </span>
                            @else
                                <span class="badge badge-danger">
                                    NOT RECONCILED
                                </span>
                            @endif
                        </td>

                        <td>
                            {{ $history->executor?->name ?? '-' }}
                        </td>

                        <td>
                            <a
                                href="{{ route(
                                    'erp.inventory.reconciliation.history.detail',
                                    $history
                                ) }}"
                                class="btn btn-sm btn-primary"
                            >
                                Detail
                            </a>
                        </td>

                    </tr>

                @empty

                    <tr>
                        <td
                            colspan="10"
                            class="text-center"
                        >
                            Belum ada reconciliation history.
                        </td>
                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

    <div class="card-footer">
        {{ $histories->links() }}
    </div>

</div>

@stop