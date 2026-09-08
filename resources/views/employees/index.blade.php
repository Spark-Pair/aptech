@extends('layouts.app')
@section('title', 'Employee Records')
@section('subtitle', 'Select an employee to review monthly attendance')
@section('content')
<div class="section-toolbar"><p>Employees are shown first. Click any row to load that employee's details and daily records below.</p><a class="btn btn-success btn-sm" href="{{ route('employees.create') }}"><i class="fa fa-plus"></i> Add Employee</a></div>
@include('partials.filters')
@include('partials.employee-table')
@if($selectedEmployee)
<div id="employee-details" style="margin-top:18px">
<div class="table-header">Selected Employee Details <span class="pull-right"><a style="color:#fff" href="{{ route('employees.edit',$selectedEmployee) }}"><i class="fa fa-pencil"></i> Edit</a></span></div>
<div class="table-responsive"><table class="table table-striped table-bordered"><thead><tr><th>Machine Code</th><th>Employee Name</th><th>Department</th><th>Designation</th><th>Shift</th><th>Shift Hours</th></tr></thead><tbody><tr><td>{{ $selectedEmployee->empid }}</td><td>{{ $selectedEmployee->name }}</td><td>{{ $selectedEmployee->department }}</td><td>{{ $selectedEmployee->designation }}</td><td>{{ $selectedEmployee->shift?->name ?? 'Not assigned' }}</td><td>@if($selectedEmployee->shift){{ date('h:i A',strtotime($selectedEmployee->shift->start_time)) }} – {{ date('h:i A',strtotime($selectedEmployee->shift->end_time)) }} ({{ intdiv($selectedEmployee->shift->duration_minutes,60) }}h {{ $selectedEmployee->shift->duration_minutes%60 }}m)@else—@endif</td></tr></tbody></table></div>
<div class="table-header">{{ \Carbon\Carbon::parse($month.'-01')->format('F Y') }} — Daily Attendance</div>
@php($attendances=$selectedAttendances)
@include('partials.attendance-table',['showEmployee'=>false])
<p class="help-block"><strong>Early Min</strong> = minutes the employee left before the assigned shift end time. <strong>Late Min</strong> = minutes the employee arrived after shift start time. Attendance rows can be manually corrected using Edit.</p>
</div>
@endif
@endsection
