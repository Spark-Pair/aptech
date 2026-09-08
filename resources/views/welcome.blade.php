@extends('layouts.app')
@section('title', 'Dashboard')
@section('subtitle', 'Employee attendance overview')
@section('content')
<div class="section-toolbar"><p><strong>{{ $employeeCount }}</strong> employees &middot; <span class="green">{{ $activeCount }} active</span></p><a class="btn btn-success btn-sm" href="{{ route('employees.create') }}"><i aria-hidden="true" class="fa fa-plus"></i> Add Employee</a></div>
@include('partials.filters')
<x-summary :summary="$summary" />
@include('partials.employee-table')
@endsection
