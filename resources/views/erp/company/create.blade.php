@extends('adminlte::page')

@section('title', 'Create Company')

@section('content_header')
    <h1>Create Company</h1>
@stop

@section('content')

<div class="card">
    <div class="card-body">

        <form method="POST"
              action="{{ route('companies.store') }}">

            @csrf

            <div class="form-group">
                <label>Code</label>
                <input type="text"
                       name="code"
                       class="form-control"
                       required>
            </div>

            <div class="form-group">
                <label>Name</label>
                <input type="text"
                       name="name"
                       class="form-control"
                       required>
            </div>

            <div class="form-group">
                <label>Phone</label>
                <input type="text"
                       name="phone"
                       class="form-control">
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email"
                       name="email"
                       class="form-control">
            </div>

            <div class="form-group">
                <label>Address</label>
                <textarea name="address"
                          class="form-control"></textarea>
            </div>

            <div class="form-check mb-3">
                <input type="checkbox"
                       name="is_active"
                       value="1"
                       checked>
                Active
            </div>

            <button class="btn btn-success">
                Save
            </button>

            <a href="{{ route('companies.index') }}"
               class="btn btn-secondary">
                Back
            </a>

        </form>

    </div>
</div>

@stop