@extends('layouts.app')
@section('title', 'Employee Details')
@section('subtitle', $employee->name)
@section('content')
<div class="section-toolbar"><a href="{{ route('employees.index',['month'=>$month]) }}"><i aria-hidden="true" class="fa fa-arrow-left"></i> Employee Records</a><a class="btn btn-info btn-sm" href="{{ route('employees.edit', $employee) }}"><i aria-hidden="true" class="fa fa-pencil"></i> Edit Employee</a></div>
<div class="table-header">Selected Employee Details</div>
<div class="table-responsive"><table class="table table-striped table-bordered"><thead><tr><th>Machine Code</th><th>Employee Name</th><th>Department</th><th>Designation</th><th>Joining Date</th><th>Monthly Salary</th><th>Status</th></tr></thead><tbody><tr><td>{{ $employee->empid }}</td><td>{{ $employee->name }}</td><td>{{ $employee->department }}</td><td>{{ $employee->designation }}</td><td>{{ $employee->joining_date->format('d M Y') }}</td><td>{{ number_format($employee->salary,2) }}</td><td>{{ $employee->is_active ? 'Active' : 'Inactive' }}</td></tr></tbody></table></div>
<form method="get" class="report-filters well well-sm"><x-field name="month" label="Attendance Month" type="month" :value="$month" required /><div class="filter-actions"><button class="btn btn-info btn-sm">Show Attendance</button><button class="btn btn-default btn-sm" type="button" data-print><i aria-hidden="true" class="fa fa-print"></i> Print</button></div></form>
<x-summary :summary="$summary" />
<div class="table-header">{{ \Carbon\Carbon::parse($month.'-01')->format('F Y') }} &mdash; Daily Attendance</div>
@include('partials.attendance-table', ['showEmployee' => false])
@endsection
