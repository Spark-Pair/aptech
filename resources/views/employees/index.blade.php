@extends('layouts.app')
@section('title', 'Employee Records')
@section('subtitle', 'Select an employee to review monthly attendance')
@section('content')
<div class="section-toolbar"><p>Employees are shown first. Click any row to load that employee's details and daily records below.</p><a class="btn btn-success btn-sm" href="{{ route('employees.create') }}" data-ajax-link><i class="fa fa-plus"></i> Add Employee</a></div>
@include('partials.filters')
@include('partials.employee-table')
@if($selectedEmployee)
<div id="employee-details" style="margin-top:18px">
<div class="table-header">Selected Employee Details <span class="pull-right"><a href="{{ route('employees.edit',$selectedEmployee) }}" class="btn btn-xs btn-info" data-ajax-link><i class="fa fa-pencil"></i> Edit Employee</a> <form method="post" action="{{ route('employees.destroy',$selectedEmployee) }}" style="display:inline" onsubmit="return confirm('Delete {{ addslashes($selectedEmployee->name) }}? Their attendance history will also be deleted. This cannot be undone.');">@csrf @method('delete')<button type="submit" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i> Delete Employee</button></form></span></div>
<div class="table-responsive"><table class="table table-striped table-bordered"><thead><tr><th>Machine Code</th><th>Employee Name</th><th>Department</th><th>Designation</th><th>Shift</th><th>Shift Hours</th></tr></thead><tbody><tr><td>{{ $selectedEmployee->empid }}</td><td>{{ $selectedEmployee->name }}</td><td>{{ $selectedEmployee->department }}</td><td>{{ $selectedEmployee->designation }}</td><td>{{ $selectedEmployee->shift?->name ?? 'Not assigned' }}</td><td>@if($selectedEmployee->shift){{ date('h:i A',strtotime($selectedEmployee->shift->start_time)) }} – {{ date('h:i A',strtotime($selectedEmployee->shift->end_time)) }} ({{ intdiv($selectedEmployee->shift->duration_minutes,60) }}h {{ $selectedEmployee->shift->duration_minutes%60 }}m)@else—@endif</td></tr></tbody></table></div>
@if($selectedSummary)
<x-summary :summary="collect($selectedSummary)->only(['Present','Absent','Off Day','Leave'])->all()" />
<div class="row report-summary">
@foreach(['Working Days'=>'briefcase','Total Records'=>'list-alt','Early Min'=>'sign-out','Late Min'=>'clock-o'] as $label => $icon)
<div class="col-xs-6 col-md-3"><div class="infobox infobox-blue">
<div class="infobox-icon"><i aria-hidden="true" class="ace-icon fa fa-{{ $icon }}"></i></div>
<div class="infobox-data"><span class="infobox-data-number">{{ number_format($selectedSummary[$label] ?? 0) }}</span><div class="infobox-content">{{ $label }}</div></div>
</div></div>
@endforeach
</div>
@endif
<div class="table-header">{{ \Carbon\Carbon::parse($month.'-01')->format('F Y') }} — Daily Attendance <span class="pull-right">{{ number_format($selectedAttendances->count()) }} records</span></div>
@php($attendances=$selectedAttendances)
@include('partials.attendance-table',['showEmployee'=>false])
<p class="help-block"><strong>Early Min</strong> = minutes the employee left before the assigned shift end time. <strong>Late Min</strong> = minutes the employee arrived after shift start time. Attendance rows can be manually corrected using Edit.</p>
</div>
@endif
@endsection
