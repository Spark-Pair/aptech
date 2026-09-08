@extends('layouts.app')
@section('title', 'Employee Leaves')
@section('subtitle', 'Record employee leave days')
@section('content')
<div class="widget-box"><div class="widget-header"><h4 class="widget-title"><i aria-hidden="true" class="fa fa-calendar-o"></i> Record Leave</h4></div><div class="widget-body"><div class="widget-main">
<p>Record approved leave for an active employee. Sundays are excluded; dates with attendance punches cannot be overwritten.</p>
<form method="post" action="{{ route('leaves.store') }}">@csrf
<div class="row"><div class="col-md-4 form-group"><label for="empid">Employee</label><select name="empid" id="empid" class="form-control" required><option value="">Select an employee</option>@foreach($employees as $employee)<option value="{{ $employee->empid }}" @selected(old('empid') == $employee->empid)>{{ $employee->name }} ({{ $employee->empid }})</option>@endforeach</select></div>
<div class="col-md-4"><x-field name="from" label="From" type="date" required /></div><div class="col-md-4"><x-field name="to" label="To" type="date" required /></div></div>
<button type="submit" class="btn btn-info"><i aria-hidden="true" class="fa fa-check"></i> Record Leave</button>
</form>
</div></div></div>
<form method="get" class="report-filters well well-sm"><x-field name="month" label="Month" type="month" :value="$month" required /><div class="filter-actions"><button class="btn btn-info btn-sm">Show Leaves</button></div></form>
<div class="table-header">Recorded Leave Days <span class="pull-right">{{ $attendances->total() }} records</span></div>
@include('partials.attendance-table')
@endsection
