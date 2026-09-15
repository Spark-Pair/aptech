@extends('layouts.app')
@section('title', 'Shift Management')
@section('subtitle', 'Configure working hours used for attendance calculations')
@section('content')
<div class="widget-box">
<div class="widget-header"><h4 class="widget-title"><i class="fa fa-clock-o"></i> Configured Shifts</h4><div class="widget-toolbar"><button type="button" class="btn btn-xs btn-primary" data-toggle="modal" data-target="#shift-create-modal"><i class="fa fa-plus"></i> Add Shift</button></div></div>
<div class="widget-body"><div class="widget-main no-padding">
@forelse($shifts as $shift)
<div class="management-list-row">
<div class="management-list-icon"><i class="fa fa-clock-o"></i></div>
<div class="management-list-content"><strong>{{ $shift->name }}</strong><small>{{ date('h:i A',strtotime($shift->start_time)) }} – {{ date('h:i A',strtotime($shift->end_time)) }} &middot; {{ intdiv($shift->duration_minutes,60) }}h {{ $shift->duration_minutes%60 }}m &middot; {{ $shift->employees_count }} {{ Str::plural('employee',$shift->employees_count) }}</small></div>
<span class="label label-{{ $shift->is_active ? 'success' : 'default' }}">{{ $shift->is_active ? 'Active' : 'Inactive' }}</span>
<button type="button" class="btn btn-xs btn-white btn-info" data-toggle="modal" data-target="#shift-edit-{{ $shift->id }}"><i class="fa fa-pencil"></i> Manage</button>
</div>
@empty
<div class="empty-state"><i class="fa fa-clock-o"></i><p>No shifts configured yet.</p></div>
@endforelse
</div></div></div>
<p class="help-block">Shift duration is calculated automatically from start and end time. Overnight shifts are supported.</p>

<div class="modal fade" id="shift-create-modal" tabindex="-1" role="dialog"><div class="modal-dialog modal-sm" role="document"><div class="modal-content">
<form method="post" action="{{ route('shifts.store') }}" data-no-ajax>@csrf
<div class="modal-header"><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button><h4 class="modal-title">Add Shift</h4></div>
<div class="modal-body"><x-field name="name" label="Shift Name" placeholder="Morning Shift" required /><x-field name="start_time" label="Start Time" type="time" required /><x-field name="end_time" label="End Time" type="time" required /></div>
<div class="modal-footer"><button type="button" class="btn btn-sm btn-default" data-dismiss="modal">Cancel</button><button class="btn btn-sm btn-primary"><i class="fa fa-plus"></i> Add Shift</button></div>
</form></div></div></div>

@foreach($shifts as $shift)
<div class="modal fade" id="shift-edit-{{ $shift->id }}" tabindex="-1" role="dialog"><div class="modal-dialog modal-sm" role="document"><div class="modal-content">
<form method="post" action="{{ route('shifts.update',$shift) }}" data-no-ajax>@csrf @method('put')
<div class="modal-header"><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button><h4 class="modal-title">Manage Shift</h4></div>
<div class="modal-body"><div class="form-group"><label>Shift Name</label><input class="form-control" name="name" value="{{ $shift->name }}" required></div><div class="form-group"><label>Start Time</label><input class="form-control" type="time" name="start_time" value="{{ substr($shift->start_time,0,5) }}" required></div><div class="form-group"><label>End Time</label><input class="form-control" type="time" name="end_time" value="{{ substr($shift->end_time,0,5) }}" required></div><div class="form-group"><label>Status</label><select class="form-control" name="is_active"><option value="1" @selected($shift->is_active)>Active</option><option value="0" @selected(!$shift->is_active)>Inactive</option></select></div></div>
<div class="modal-footer"><button type="button" class="btn btn-sm btn-default" data-dismiss="modal">Cancel</button><button class="btn btn-sm btn-primary"><i class="fa fa-check"></i> Save Changes</button></div>
</form></div></div></div>
@endforeach
@endsection
