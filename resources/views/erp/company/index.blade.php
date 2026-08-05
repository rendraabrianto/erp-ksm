@extends('adminlte::page')

@section('title', 'Companies')

@section('content_header')
    <h1>Company Management</h1>
@stop

@section('content')

<div class="card">
    <div class="card-body">

        <div class="mb-3">
            <a href="{{ route('companies.create') }}"
            class="btn btn-primary">
                <i class="fas fa-plus"></i>
                Add Company
            </a>
        </div>

        <table id="company-table"
       class="table table-bordered table-striped">

            <thead>
                <tr>
                    <th>ID</th>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                @foreach($companies as $company)
                <tr>

                    <td>{{ $company->id }}</td>

                    <td>{{ $company->code }}</td>

                    <td>{{ $company->name }}</td>

                    <td>
                        @if($company->is_active)
                            <span class="badge badge-success">
                                Active
                            </span>
                        @else
                            <span class="badge badge-danger">
                                Inactive
                            </span>
                        @endif
                    </td>

                    <td>

                        <a href="{{ route('companies.edit',$company) }}"
                        class="btn btn-warning btn-sm">

                            Edit

                        </a>

                                    <a href="{{ route('companies.destroy', $company) }}" method="POST"
                                        class="inline-block"
                                        onsubmit="return confirm('Hapus perusahaan ini?')">
                                        
                                        DELETE

                                        
                    </a>

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
            $('#company-table').DataTable({
                responsive: true,
                autoWidth: false,
                pageLength: 10,
                order: [[0, 'desc']],
            });
        });
    </script>
@stop