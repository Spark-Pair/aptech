@extends('layouts.app')
@section('title', 'Employee Leaves')
@section('subtitle', 'Record employee leave days')
@section('content')
<div class="section-toolbar"><p>Approved employee leave days. Sundays and dates with attendance punches are protected.</p><button type="button" class="btn btn-info btn-sm" data-toggle="modal" data-target="#record-leave-modal"><i class="fa fa-plus"></i> Record Leave</button></div>
<form method="get" class="report-filters well well-sm"><x-field name="month" label="Month" type="month" :value="$month" required /><div class="filter-actions"><button class="btn btn-info btn-sm">Show Leaves</button></div></form>
<div class="table-header">Recorded Leave Days <span class="pull-right">{{ $attendances->total() }} records</span></div>
@include('partials.attendance-table')

<div class="modal fade" id="record-leave-modal" tabindex="-1" role="dialog"><div class="modal-dialog" role="document"><div class="modal-content">
<form method="post" action="{{ route('leaves.store') }}">@csrf
<div class="modal-header"><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button><h4 class="modal-title">Record Employee Leave</h4></div>
<div class="modal-body"><p class="help-block">Sundays are excluded and existing attendance punches are not overwritten.</p><div class="form-group"><label for="leave-empid">Employee</label><select name="empid" id="leave-empid" class="form-control" required><option value="">Select an employee</option>@foreach($employees as $employee)<option value="{{ $employee->empid }}">{{ $employee->name }} ({{ $employee->empid }})</option>@endforeach</select></div><div class="row"><div class="col-sm-6"><x-field name="from" label="From" type="date" required /></div><div class="col-sm-6"><x-field name="to" label="To" type="date" required /></div></div></div>
<div class="modal-footer"><button type="button" class="btn btn-sm btn-default" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-sm btn-info"><i class="fa fa-check"></i> Record Leave</button></div>
</form></div></div></div>
@endsection
