@extends('adminlte::page')

@section('title', 'Create Item')

@section('content_header')
    <h1>Create Items</h1>
@stop

@section('content')

<div class="card">
    <div class="card-body">

        <form
            method="POST"
            action="{{ route('items.store') }}"
        >
        @csrf

            <div class="form-group">
                <label>Code</label>
                <input
                    type="text"
                    name="code"
                    class="form-control"
                    required
                >
            </div>
            <div class="form-group">
                <label>Name</label>
                <input
                    type="text"
                    name="name"
                    class="form-control"
                    required
                >
            </div>
            <div class="form-group">
                <label>Category</label>

                <select
                    name="item_category_id"
                    class="form-control"
                    required
                >
                    @foreach($categories as $category)
                        <option
                            value="{{ $category->id }}">
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>UOM</label>

                <select
                    name="uom_id"
                    class="form-control"
                    required
                >
                    @foreach($uoms as $uom)
                        <option
                            value="{{ $uom->id }}">
                            {{ $uom->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label>MINIMUM STOCK</label>
                <input
                    type="number"
                    step="0.0001"
                    name="minimum_stock"
                    class="form-control"
                >
            </div>
                <label>MAXIMUM STOCK</label>
                <input
                    type="number"
                    step="0.0001"
                    name="maximum_stock"
                    class="form-control"
                >
            </div>    

            <button
                type="submit" class="btn btn-primary">
                Save
            </button>

            {{-- <a href="{{ route('items.index') }}"
               class="btn btn-secondary">
                Back
            </a> --}}

        </form>

    </div>
</div>

@stop