@extends('adminlte::page')

@section('title', 'Branch Management')

@section('content_header')
    <h1>Branch Management</h1>
@stop

@section('content')

<div class="card">

    <div class="card-header">
        <a href="#"
           class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Add Branch
        </a>
    </div>

    <div class="card-body">

        <table id="branches-table"
               class="table table-bordered table-striped">

            <thead>
                <tr>
                    <th>ID</th>
                    <th>Company</th>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>

            @foreach($branches as $branch)

                <tr>
                    <td>{{ $branch->id }}</td>

                    <td>
                        {{ $branch->company?->name }}
                    </td>

                    <td>{{ $branch->code }}</td>

                    <td>{{ $branch->name }}</td>

                    <td>
                        @if($branch->is_active)
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
    $('#branches-table').DataTable();
});
</script>

@stop