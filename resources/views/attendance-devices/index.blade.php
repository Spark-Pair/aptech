@extends('layouts.app')
@section('title','Branches & Devices')
@section('subtitle','Manage attendance locations and machines')
@section('content')
@if(session('provision_token'))
<div class="alert alert-warning"><strong>Provisioning token (shown once):</strong> <code style="word-break:break-all">{{ session('provision_token') }}</code><br><small>Keep this private. It is only needed when provisioning the Local Agent.</small></div>
@endif

<div class="row">
    <div class="col-md-5">
        <div class="widget-box">
            <div class="widget-header">
                <h4 class="widget-title"><i class="fa fa-building-o"></i> Branches</h4>
                <div class="widget-toolbar"><button type="button" class="btn btn-xs btn-primary" data-toggle="modal" data-target="#branch-create-modal"><i class="fa fa-plus"></i> Add Branch</button></div>
            </div>
            <div class="widget-body"><div class="widget-main no-padding">
                @forelse($branches as $branch)
                <div class="management-list-row">
                    <div class="management-list-icon"><i class="fa fa-building-o"></i></div>
                    <div class="management-list-content"><strong>{{ $branch->name }}</strong><small>{{ $branch->code }} &middot; {{ $branch->attendanceSyncAgents->count() }} {{ Str::plural('device',$branch->attendanceSyncAgents->count()) }}</small></div>
                    <span class="label label-{{ $branch->is_active ? 'success' : 'default' }}">{{ $branch->is_active ? 'Active' : 'Inactive' }}</span>
                    <button type="button" class="btn btn-xs btn-white btn-info" data-toggle="modal" data-target="#branch-edit-{{ $branch->id }}"><i class="fa fa-pencil"></i> Manage</button>
                </div>
                @empty
                <div class="empty-state"><i class="fa fa-building-o"></i><br>No branches yet.<br><small>Add a branch to organize attendance devices.</small></div>
                @endforelse
            </div></div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="widget-box">
            <div class="widget-header">
                <h4 class="widget-title"><i class="fa fa-clock-o"></i> Attendance Devices</h4>
                <div class="widget-toolbar"><button type="button" class="btn btn-xs btn-primary" data-toggle="modal" data-target="#device-create-modal" @disabled($branches->where('is_active',true)->isEmpty())><i class="fa fa-plus"></i> Add Device</button></div>
            </div>
            <div class="widget-body"><div class="widget-main no-padding">
                @php($deviceCount = 0)
                @foreach($branches as $branch)
                    @foreach($branch->attendanceSyncAgents as $agent)
                    @php($deviceCount++)
                    <div class="management-list-row device-list-row">
                        <div class="management-list-icon"><i class="fa fa-clock-o"></i></div>
                        <div class="management-list-content"><strong>{{ $agent->name }}</strong><small>{{ $branch->name }} &middot; {{ $agent->device_ip }}:{{ $agent->device_port ?: 4370 }} &middot; {{ $agent->device_identifier }}</small><small class="text-muted">Last heartbeat: {{ $agent->last_heartbeat_at?->diffForHumans() ?? 'Never' }}</small></div>
                        <span class="label label-{{ $agent->is_active ? 'success' : 'default' }}">{{ $agent->is_active ? 'Active' : 'Inactive' }}</span>
                        <button type="button" class="btn btn-xs btn-white btn-info" data-toggle="modal" data-target="#device-edit-{{ $agent->id }}"><i class="fa fa-cog"></i> Manage</button>
                    </div>
                    @endforeach
                @endforeach
                @if($deviceCount === 0)
                <div class="empty-state"><i class="fa fa-clock-o"></i><br>No attendance devices yet.<br><small>Add a device and assign it to a branch.</small></div>
                @endif
            </div></div>
        </div>
    </div>
</div>

<div class="modal fade" id="branch-create-modal" tabindex="-1" role="dialog" aria-labelledby="branch-create-title">
 <div class="modal-dialog modal-sm" role="document"><div class="modal-content">
  <form method="post" action="{{ route('attendance-devices.branches.store') }}">@csrf
   <div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button><h4 class="modal-title" id="branch-create-title">Add Branch</h4></div>
   <div class="modal-body"><div class="form-group"><label>Name</label><input class="form-control" name="name" required maxlength="120" placeholder="Main Office"></div><div class="form-group"><label>Code</label><input class="form-control" name="code" required maxlength="50" placeholder="main-office"><p class="help-block">Use a short stable code for this location.</p></div></div>
   <div class="modal-footer"><button type="button" class="btn btn-sm btn-default" data-dismiss="modal">Cancel</button><button class="btn btn-sm btn-primary" type="submit">Add Branch</button></div>
  </form>
 </div></div>
</div>

@foreach($branches as $branch)
<div class="modal fade" id="branch-edit-{{ $branch->id }}" tabindex="-1" role="dialog" aria-labelledby="branch-edit-title-{{ $branch->id }}">
 <div class="modal-dialog modal-sm" role="document"><div class="modal-content">
  <form method="post" action="{{ route('attendance-devices.branches.update',$branch) }}">@csrf @method('PUT')
   <div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button><h4 class="modal-title" id="branch-edit-title-{{ $branch->id }}">Manage Branch</h4></div>
   <div class="modal-body"><div class="form-group"><label>Name</label><input class="form-control" name="name" value="{{ $branch->name }}" required></div><div class="form-group"><label>Code</label><input class="form-control" name="code" value="{{ $branch->code }}" required></div><div class="form-group"><label>Status</label><select class="form-control" name="is_active"><option value="1" @selected($branch->is_active)>Active</option><option value="0" @selected(!$branch->is_active)>Inactive</option></select></div></div>
   <div class="modal-footer"><button type="button" class="btn btn-sm btn-default" data-dismiss="modal">Cancel</button><button class="btn btn-sm btn-primary" type="submit">Save Changes</button></div>
  </form>
 </div></div>
</div>
@endforeach

<div class="modal fade" id="device-create-modal" tabindex="-1" role="dialog" aria-labelledby="device-create-title">
 <div class="modal-dialog" role="document"><div class="modal-content">
  <form method="post" action="{{ route('attendance-devices.devices.store') }}">@csrf
   <div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button><h4 class="modal-title" id="device-create-title">Add Attendance Device</h4></div>
   <div class="modal-body">
    <div class="row"><div class="col-sm-6 form-group"><label>Branch</label><select class="form-control" name="branch_id" required>@foreach($branches->where('is_active',true) as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></div><div class="col-sm-6 form-group"><label>Device Name</label><input class="form-control" name="name" required placeholder="Office Attendance"></div></div>
    <div class="row"><div class="col-sm-6 form-group"><label>Device Identifier</label><input class="form-control" name="device_identifier" required placeholder="zk-office-1"></div><div class="col-sm-6 form-group"><label>Device IP</label><input class="form-control" name="device_ip" required placeholder="192.168.100.16"></div></div>
    <div class="row"><div class="col-sm-3 form-group"><label>Port</label><input class="form-control" type="number" name="device_port" value="4370" required></div><div class="col-sm-5 form-group"><label>Timezone</label><input class="form-control" name="device_timezone" value="Asia/Karachi" required></div><div class="col-sm-4 form-group"><label>Timeout</label><div class="input-group"><input class="form-control" type="number" name="device_timeout" value="5" min="1" max="60" required><span class="input-group-addon">sec</span></div></div></div>
   </div>
   <div class="modal-footer"><button type="button" class="btn btn-sm btn-default" data-dismiss="modal">Cancel</button><button class="btn btn-sm btn-primary" type="submit">Add Device</button></div>
  </form>
 </div></div>
</div>

@foreach($branches as $branch) @foreach($branch->attendanceSyncAgents as $agent)
<div class="modal fade" id="device-edit-{{ $agent->id }}" tabindex="-1" role="dialog" aria-labelledby="device-edit-title-{{ $agent->id }}">
 <div class="modal-dialog" role="document"><div class="modal-content">
  <form method="post" action="{{ route('attendance-devices.devices.update',$agent) }}">@csrf @method('PUT')
   <div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button><h4 class="modal-title" id="device-edit-title-{{ $agent->id }}">Manage {{ $agent->name }}</h4></div>
   <div class="modal-body">
    <div class="row"><div class="col-sm-6 form-group"><label>Branch</label><select class="form-control" name="branch_id">@foreach($branches as $b)<option value="{{ $b->id }}" @selected($agent->branch_id==$b->id)>{{ $b->name }}</option>@endforeach</select></div><div class="col-sm-6 form-group"><label>Device Name</label><input class="form-control" name="name" value="{{ $agent->name }}" required></div></div>
    <div class="row"><div class="col-sm-6 form-group"><label>Device Identifier</label><input class="form-control" name="device_identifier" value="{{ $agent->device_identifier }}" required></div><div class="col-sm-6 form-group"><label>Device IP</label><input class="form-control" name="device_ip" value="{{ $agent->device_ip }}" required></div></div>
    <div class="row"><div class="col-sm-3 form-group"><label>Port</label><input class="form-control" type="number" name="device_port" value="{{ $agent->device_port ?: 4370 }}" required></div><div class="col-sm-5 form-group"><label>Timezone</label><input class="form-control" name="device_timezone" value="{{ $agent->device_timezone ?: 'Asia/Karachi' }}" required></div><div class="col-sm-4 form-group"><label>Timeout</label><div class="input-group"><input class="form-control" type="number" name="device_timeout" value="{{ $agent->device_timeout ?: 5 }}" min="1" max="60" required><span class="input-group-addon">sec</span></div></div></div>
    <div class="checkbox"><label><input type="checkbox" name="is_active" value="1" @checked($agent->is_active)> Device active</label></div>
    @if($agent->last_error)<div class="alert alert-danger" style="margin-bottom:0"><i class="fa fa-exclamation-circle"></i> {{ $agent->last_error }}</div>@endif
   </div>
   <div class="modal-footer"><button type="button" class="btn btn-sm btn-default" data-dismiss="modal">Cancel</button><button class="btn btn-sm btn-primary" type="submit">Save Changes</button></div>
  </form>
  <div class="modal-credential-action"><form method="post" action="{{ route('attendance-devices.devices.rotate-token',$agent) }}">@csrf<button class="btn btn-link btn-sm" type="submit" onclick="return confirm('Rotate this Local Agent credential? The existing agent will stop authenticating until reprovisioned.')"><i class="fa fa-refresh"></i> Rotate Local Agent Credential</button></form></div>
 </div></div>
</div>
@endforeach @endforeach
@endsection
