@extends('adminlte::page')

@section('title', 'User Management')

@section('content_header')
    <h1>User Management</h1>
@stop

@section('content')

<div class="card">

    <div class="card-header">

        <a href="#"
           class="btn btn-primary">

            <i class="fas fa-plus"></i>
            Add User

        </a>

    </div>

    <div class="card-body">

        <table id="users-table"
               class="table table-bordered table-striped">

            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Company</th>
                    <th>Branch</th>
                    <th>Role</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>

            @foreach($users as $user)

                <tr>

                    <td>{{ $user->id }}</td>

                    <td>{{ $user->name }}</td>

                    <td>{{ $user->email }}</td>

                    <td>{{ $user->company?->name }}</td>

                    <td>{{ $user->branch?->name }}</td>

                    <td>
                        {{ $user->roles->pluck('name')->implode(', ') }}
                    </td>

                    <td>

                        @if($user->is_active)
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
    $('#users-table').DataTable();
});
</script>

@stop