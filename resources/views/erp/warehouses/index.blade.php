@extends('adminlte::page')

@section('title', 'Warehouse Management')

@section('content_header')
    <h1>Warehouse Management</h1>
@stop

@section('content')

<div class="card">

    <div class="card-header">

        <a href="{{ route('warehouses.create') }}"
           class="btn btn-primary">

            <i class="fas fa-plus"></i>
            Add Warehouse

        </a>

    </div>

    <div class="card-body">

        <table id="warehouse-table"
               class="table table-bordered table-striped">

            <thead>
                <tr>
                    <th>ID</th>
                    <th>Company</th>
                    <th>Branch</th>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>

            @foreach($warehouses as $warehouse)

                <tr>

                    <td>{{ $warehouse->id }}</td>

                    <td>{{ $warehouse->company?->name }}</td>

                    <td>{{ $warehouse->branch?->name }}</td>

                    <td>{{ $warehouse->code }}</td>

                    <td>{{ $warehouse->name }}</td>

                    <td>
                        @if($warehouse->is_active)
                            <span class="badge badge-success">
                                Active
                            </span>
                        @else
                            <span class="badge badge-danger">
                                Inactive
                            </span>
                        @endif
                    </td>

                </tr>

            @endforeach

            </tbody>

        </table>

    </div>

</div>

@stop

@section('js')

<script>
$(function () {
    $('#warehouse-table').DataTable();
});
</script>

@stop