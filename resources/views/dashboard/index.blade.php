@extends('adminlte::page')

@section('title', 'Dashboard ERP KSM')

@section('content_header')
    <h1>Dashboard ERP KSM</h1>
@stop

@section('content')

<div class="row">

    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3>{{ $dashboard->companies }}</h3>
                <p>Companies</p>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3>{{ $dashboard->branches }}</h3>
                <p>Branches</p>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3>{{ $dashboard->users }}</h3>
                <p>Users</p>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-6">
        <div class="small-box bg-danger">
            <div class="inner">
                <h3>{{ $dashboard->roles }}</h3>
                <p>Roles</p>
            </div>
        </div>
    </div>

</div>

@stop