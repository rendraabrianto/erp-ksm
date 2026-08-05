@extends('adminlte::page')

@section('title', 'Edit Company')

@section('content_header')
    <h1>Edit Company</h1>
@stop

@section('content')

<div class="card">
    <div class="card-body">

        <form method="POST"
              action="{{ route('companies.update',$company) }}">

            @csrf
            @method('PUT')

            <div class="form-group">
                <label>Code</label>
                <input
                    type="text"
                    name="code"
                    class="form-control"
                    value="{{ old('code',$company->code) }}">
            </div>

            <div class="form-group">
                <label>Name</label>
                <input
                    type="text"
                    name="name"
                    class="form-control"
                    value="{{ old('name',$company->name) }}">
            </div>

            <div class="form-group">
                <label>Phone</label>
                <input
                    type="text"
                    name="phone"
                    class="form-control"
                    value="{{ old('phone',$company->phone) }}">
            </div>

            <div class="form-group">
                <label>Email</label>
                <input
                    type="email"
                    name="email"
                    class="form-control"
                    value="{{ old('email',$company->email) }}">
            </div>

            <div class="form-group">
                <label>Address</label>
                <textarea
                    name="address"
                    class="form-control">{{ old('address',$company->address) }}</textarea>
            </div>

            <div class="form-check mb-3">
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    {{ $company->is_active ? 'checked' : '' }}>
                Active
            </div>

            <button class="btn btn-primary">
                Update
            </button>

        </form>

    </div>
</div>

@stop