@extends('layouts.app')
@section('title', 'Employee Records')
@section('subtitle', 'Monthly records and employee management')
@section('content')
<div class="section-toolbar"><p>Manage employees and review monthly attendance.</p><a class="btn btn-success btn-sm" href="{{ route('employees.create') }}"><i aria-hidden="true" class="fa fa-plus"></i> Add Employee</a></div>
@include('partials.filters')
@include('partials.employee-table')
@endsection
