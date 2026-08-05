@extends('adminlte::page')

@section('title', 'Role Management')

@section('content_header')
    <h1>Role Management</h1>
@stop

@section('content')

<div class="card">

    <div class="card-header">

        <a href="#"
           class="btn btn-primary">

            <i class="fas fa-plus"></i>
            Add Role

        </a>

    </div>

    <div class="card-body">

        <table id="roles-table"
               class="table table-bordered table-striped">

            <thead>
                <tr>
                    <th>ID</th>
                    <th>Role Name</th>
                    <th>Total Users</th>
                </tr>
            </thead>

            <tbody>

            @foreach($roles as $role)

                <tr>

                    <td>{{ $role->id }}</td>

                    <td>{{ $role->name }}</td>

                    <td>{{ $role->users_count }}</td>

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
    $('#roles-table').DataTable();
});
</script>

@stop