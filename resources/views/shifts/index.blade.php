@extends('layouts.app')
@section('title', 'Shift Management')
@section('subtitle', 'Configure working hours used for attendance calculations')
@section('content')
<div class="widget-box"><div class="widget-header"><h4 class="widget-title"><i class="fa fa-clock-o"></i> Add Shift</h4></div><div class="widget-body"><div class="widget-main">
<form method="post" action="{{ route('shifts.store') }}" class="row">@csrf
<div class="col-sm-4"><x-field name="name" label="Shift Name" placeholder="Morning Shift" required /></div>
<div class="col-sm-3"><x-field name="start_time" label="Start Time" type="time" required /></div>
<div class="col-sm-3"><x-field name="end_time" label="End Time" type="time" required /></div>
<div class="col-sm-2"><label>&nbsp;</label><button class="btn btn-success btn-block"><i class="fa fa-plus"></i> Add Shift</button></div>
</form></div></div></div>
<div class="table-header">Configured Shifts</div>
<div class="table-responsive"><table class="table table-striped table-bordered table-hover"><thead><tr><th>Shift</th><th>Start</th><th>End</th><th>Hours</th><th>Employees</th><th>Status</th><th>Save</th></tr></thead><tbody>
@forelse($shifts as $shift)<tr><form method="post" action="{{ route('shifts.update',$shift) }}">@csrf @method('put')
<td><input class="form-control" name="name" value="{{ $shift->name }}" required></td><td><input class="form-control" type="time" name="start_time" value="{{ substr($shift->start_time,0,5) }}" required></td><td><input class="form-control" type="time" name="end_time" value="{{ substr($shift->end_time,0,5) }}" required></td>
<td>{{ intdiv($shift->duration_minutes,60) }}h {{ $shift->duration_minutes%60 }}m</td><td>{{ $shift->employees_count }}</td><td><select class="form-control" name="is_active"><option value="1" @selected($shift->is_active)>Active</option><option value="0" @selected(!$shift->is_active)>Inactive</option></select></td><td><button class="btn btn-info btn-sm"><i class="fa fa-save"></i> Save</button></td></form></tr>
@empty<tr><td colspan="7" class="empty-state"><i class="fa fa-clock-o"></i><p>No shifts configured yet.</p></td></tr>@endforelse
</tbody></table></div>
<p class="help-block">Shift duration is calculated automatically from start and end time. Overnight shifts are supported.</p>
@endsection
