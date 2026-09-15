@extends('layouts.app')
@section('title','Branches & Devices')
@section('subtitle','Manage attendance locations and machines')
@section('content')
@if(session('provision_token'))
<div class="alert alert-warning"><strong>Provisioning token (shown once):</strong> <code style="word-break:break-all">{{ session('provision_token') }}</code><br><small>Keep this private. It is only needed when provisioning the Local Agent.</small></div>
@endif
<div class="row">
 <div class="col-md-5">
  <div class="widget-box"><div class="widget-header"><h4 class="widget-title">Branches</h4></div><div class="widget-body"><div class="widget-main">
   <form method="post" action="{{ route('attendance-devices.branches.store') }}" class="form-horizontal">@csrf
    <div class="form-group"><label class="col-sm-3 control-label">Name</label><div class="col-sm-9"><input class="form-control" name="name" required maxlength="120"></div></div>
    <div class="form-group"><label class="col-sm-3 control-label">Code</label><div class="col-sm-9"><input class="form-control" name="code" required maxlength="50" placeholder="main-office"></div></div>
    <div class="clearfix"><button class="btn btn-sm btn-primary pull-right" type="submit"><i class="fa fa-plus"></i> Add Branch</button></div>
   </form><hr>
   @foreach($branches as $branch)
   <form method="post" action="{{ route('attendance-devices.branches.update',$branch) }}" class="well well-sm">@csrf @method('PUT')
    <div class="row"><div class="col-xs-5"><input class="form-control" name="name" value="{{ $branch->name }}" required></div><div class="col-xs-4"><input class="form-control" name="code" value="{{ $branch->code }}" required></div><div class="col-xs-3"><select class="form-control" name="is_active"><option value="1" @selected($branch->is_active)>Active</option><option value="0" @selected(!$branch->is_active)>Inactive</option></select></div></div>
    <div class="text-right" style="margin-top:8px"><small class="pull-left">{{ $branch->attendanceSyncAgents->count() }} device(s)</small><button class="btn btn-xs btn-info" type="submit">Save</button></div>
   </form>
   @endforeach
  </div></div></div>
 </div>
 <div class="col-md-7">
  <div class="widget-box"><div class="widget-header"><h4 class="widget-title">Attendance Devices</h4></div><div class="widget-body"><div class="widget-main">
   <form method="post" action="{{ route('attendance-devices.devices.store') }}" class="well">@csrf
    <div class="row"><div class="col-sm-6"><label>Branch</label><select class="form-control" name="branch_id" required>@foreach($branches->where('is_active',true) as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></div><div class="col-sm-6"><label>Device Name</label><input class="form-control" name="name" required placeholder="Office Attendance"></div></div>
    <div class="row" style="margin-top:10px"><div class="col-sm-6"><label>Device Identifier</label><input class="form-control" name="device_identifier" required placeholder="zk-office-1"></div><div class="col-sm-6"><label>Device IP</label><input class="form-control" name="device_ip" required placeholder="192.168.100.16"></div></div>
    <div class="row" style="margin-top:10px"><div class="col-sm-4"><label>Port</label><input class="form-control" type="number" name="device_port" value="4370" required></div><div class="col-sm-5"><label>Timezone</label><input class="form-control" name="device_timezone" value="Asia/Karachi" required></div><div class="col-sm-3"><label>Timeout</label><input class="form-control" type="number" name="device_timeout" value="5" min="1" max="60" required></div></div>
    <div class="text-right" style="margin-top:10px"><button class="btn btn-sm btn-primary" type="submit"><i class="fa fa-plus"></i> Add Device</button></div>
   </form>
   @foreach($branches as $branch) @foreach($branch->attendanceSyncAgents as $agent)
   <form method="post" action="{{ route('attendance-devices.devices.update',$agent) }}" class="well well-sm">@csrf @method('PUT')
    <div class="clearfix"><strong>{{ $agent->name }}</strong><span class="label label-{{ $agent->is_active?'success':'default' }} pull-right">{{ $agent->is_active?'Active':'Inactive' }}</span><br><small>{{ $branch->name }} · {{ $agent->device_identifier }} · Last heartbeat: {{ $agent->last_heartbeat_at?->diffForHumans() ?? 'Never' }}</small></div>
    <div class="row" style="margin-top:8px"><div class="col-sm-4"><label>Branch</label><select class="form-control" name="branch_id">@foreach($branches as $b)<option value="{{ $b->id }}" @selected($agent->branch_id==$b->id)>{{ $b->name }}</option>@endforeach</select></div><div class="col-sm-4"><label>Name</label><input class="form-control" name="name" value="{{ $agent->name }}" required></div><div class="col-sm-4"><label>Identifier</label><input class="form-control" name="device_identifier" value="{{ $agent->device_identifier }}" required></div></div>
    <div class="row" style="margin-top:8px"><div class="col-sm-4"><label>IP Address</label><input class="form-control" name="device_ip" value="{{ $agent->device_ip }}" required></div><div class="col-sm-2"><label>Port</label><input class="form-control" type="number" name="device_port" value="{{ $agent->device_port ?: 4370 }}" required></div><div class="col-sm-3"><label>Timezone</label><input class="form-control" name="device_timezone" value="{{ $agent->device_timezone ?: 'Asia/Karachi' }}" required></div><div class="col-sm-2"><label>Timeout</label><input class="form-control" type="number" name="device_timeout" value="{{ $agent->device_timeout ?: 5 }}" required></div><div class="col-sm-1"><label>On</label><input type="checkbox" name="is_active" value="1" @checked($agent->is_active) style="margin-top:10px"></div></div>
    @if($agent->last_error)<div class="text-danger" style="margin-top:7px"><i class="fa fa-exclamation-circle"></i> {{ $agent->last_error }}</div>@endif
    <div class="text-right" style="margin-top:8px"><button class="btn btn-xs btn-info" type="submit">Save Device</button></div>
   </form>
   <form method="post" action="{{ route('attendance-devices.devices.rotate-token',$agent) }}" class="text-right" style="margin-top:-28px;margin-bottom:18px;margin-right:95px">@csrf<button class="btn btn-xs btn-warning" type="submit" onclick="return confirm('Rotate this Local Agent credential? The existing agent will stop authenticating until reprovisioned.')">Rotate Credential</button></form>
   @endforeach @endforeach
  </div></div></div>
 </div>
</div>
@endsection
