@extends('adminlte::page')

@section('title', 'Items Management')

@section('content_header')
    <h1>Items Management</h1>
@stop

@section('content')

<div class="card">

    <div class="card-header">

        <a href="{{ route('items.create') }}"
           class="btn btn-primary">

            <i class="fas fa-plus"></i>
            Add Item

        </a>

    </div>

    <div class="card-body">

        <table id="item-table"
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
            @foreach($items as $item)
            <tr>
                <td>{{ $item->code }}</td>
                <td>{{ $item->name }}</td>
                <td>{{ $item->category->name }}</td>
                <td>{{ $item->uom->symbol }}</td>
                <td>{{ $item->minimum_stock }}</td>
                <td>
                    @if($item->is_active)
                        <span class="badge bg-success">
                            Active
                        </span>
                    @else
                        <span class="badge bg-danger">
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

@section('js')
<script>
$(function () {
    $('#item-table').DataTable();
});
</script>
@endsection

@stop