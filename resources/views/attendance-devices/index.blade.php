@extends('layouts.app')
@section('title','Branches & Devices')
@section('subtitle','Manage attendance locations, devices and connectivity')
@section('content')
<div class="device-management-page">
@if(session('provision_token'))
<div class="alert alert-warning device-token-alert">
    <div class="device-token-icon"><i class="fa fa-key"></i></div>
    <div class="device-token-copy"><strong>Local Agent provisioning credential</strong><p>Shown once. Keep it private and use it only while provisioning this device.</p><code>{{ session('provision_token') }}</code></div>
</div>
@endif

<div class="device-overview">
    <div class="device-overview-card"><span class="device-overview-icon"><i class="fa fa-building-o"></i></span><div><strong>{{ $branches->count() }}</strong><span>Branches</span></div></div>
    <div class="device-overview-card"><span class="device-overview-icon"><i class="fa fa-clock-o"></i></span><div><strong>{{ $branches->sum(fn($branch) => $branch->attendanceSyncAgents->count()) }}</strong><span>Devices</span></div></div>
    <div class="device-overview-card"><span class="device-overview-icon"><i class="fa fa-check-circle-o"></i></span><div><strong>{{ $branches->sum(fn($branch) => $branch->attendanceSyncAgents->where('is_active', true)->count()) }}</strong><span>Active devices</span></div></div>
</div>

<div class="row device-management-grid">
    <div class="col-lg-4 col-md-5">
        <section class="device-panel">
            <div class="device-panel-heading"><div><h3>Branches</h3><p>Attendance locations in your company</p></div><button type="button" class="btn btn-sm btn-primary" data-toggle="collapse" data-target="#new-branch-form"><i class="fa fa-plus"></i> New Branch</button></div>
            <div id="new-branch-form" class="collapse {{ $errors->has('code') || $errors->has('name') ? 'in' : '' }}">
                <form method="post" action="{{ route('attendance-devices.branches.store') }}" class="device-create-form">@csrf
                    <div class="form-group"><label>Branch name</label><input class="form-control" name="name" required maxlength="120" placeholder="Main Office"></div>
                    <div class="form-group"><label>Branch code</label><input class="form-control" name="code" required maxlength="50" placeholder="main-office"><span class="help-block">Short stable code used to identify this location.</span></div>
                    <div class="text-right"><button class="btn btn-primary" type="submit">Create Branch</button></div>
                </form>
            </div>
            <div class="branch-list">
            @forelse($branches as $branch)
                <form method="post" action="{{ route('attendance-devices.branches.update',$branch) }}" class="branch-card">@csrf @method('PUT')
                    <div class="branch-card-top"><div class="branch-mark"><i class="fa fa-building-o"></i></div><div class="branch-title"><strong>{{ $branch->name }}</strong><span>{{ $branch->attendanceSyncAgents->count() }} {{ Str::plural('device',$branch->attendanceSyncAgents->count()) }}</span></div><span class="device-status {{ $branch->is_active ? 'is-online' : 'is-offline' }}">{{ $branch->is_active ? 'Active' : 'Inactive' }}</span></div>
                    <div class="branch-edit-grid"><div><label>Name</label><input class="form-control" name="name" value="{{ $branch->name }}" required></div><div><label>Code</label><input class="form-control" name="code" value="{{ $branch->code }}" required></div></div>
                    <div class="branch-card-actions"><select class="form-control input-sm" name="is_active"><option value="1" @selected($branch->is_active)>Active</option><option value="0" @selected(!$branch->is_active)>Inactive</option></select><button class="btn btn-sm btn-default" type="submit"><i class="fa fa-check"></i> Save</button></div>
                </form>
            @empty
                <div class="device-empty"><i class="fa fa-building-o"></i><strong>No branches yet</strong><span>Create a branch before adding attendance devices.</span></div>
            @endforelse
            </div>
        </section>
    </div>

    <div class="col-lg-8 col-md-7">
        <section class="device-panel">
            <div class="device-panel-heading"><div><h3>Attendance Devices</h3><p>Connection settings are delivered automatically to each Local Agent</p></div><button type="button" class="btn btn-sm btn-primary" data-toggle="collapse" data-target="#new-device-form" @disabled($branches->where('is_active',true)->isEmpty())><i class="fa fa-plus"></i> New Device</button></div>
            <div id="new-device-form" class="collapse">
                <form method="post" action="{{ route('attendance-devices.devices.store') }}" class="device-create-form">@csrf
                    <div class="row"><div class="col-sm-6 form-group"><label>Branch</label><select class="form-control" name="branch_id" required>@foreach($branches->where('is_active',true) as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></div><div class="col-sm-6 form-group"><label>Device name</label><input class="form-control" name="name" required placeholder="Office Attendance"></div></div>
                    <div class="row"><div class="col-sm-6 form-group"><label>Device identifier</label><input class="form-control" name="device_identifier" required placeholder="zk-office-1"><span class="help-block">Stable unique name for this machine.</span></div><div class="col-sm-6 form-group"><label>LAN IP address</label><input class="form-control" name="device_ip" required placeholder="192.168.100.16"><span class="help-block">Change this here whenever the device IP changes.</span></div></div>
                    <div class="row"><div class="col-sm-3 form-group"><label>Port</label><input class="form-control" type="number" name="device_port" value="4370" required></div><div class="col-sm-5 form-group"><label>Timezone</label><input class="form-control" name="device_timezone" value="Asia/Karachi" required></div><div class="col-sm-4 form-group"><label>Timeout (seconds)</label><input class="form-control" type="number" name="device_timeout" value="5" min="1" max="60" required></div></div>
                    <div class="text-right"><button class="btn btn-primary" type="submit">Create Device</button></div>
                </form>
            </div>

            <div class="attendance-device-list">
            @forelse($branches as $branch)
                @foreach($branch->attendanceSyncAgents as $agent)
                <article class="attendance-device-card">
                    <div class="attendance-device-summary">
                        <div class="device-avatar"><i class="fa fa-clock-o"></i></div>
                        <div class="device-primary"><div class="device-name-row"><h4>{{ $agent->name }}</h4><span class="device-status {{ $agent->is_active ? 'is-online' : 'is-offline' }}">{{ $agent->is_active ? 'Active' : 'Inactive' }}</span></div><p>{{ $branch->name }} <span>•</span> {{ $agent->device_identifier }}</p></div>
                        <div class="device-heartbeat"><span>Last heartbeat</span><strong>{{ $agent->last_heartbeat_at?->diffForHumans() ?? 'Never connected' }}</strong></div>
                    </div>
                    @if($agent->last_error)<div class="device-error"><i class="fa fa-exclamation-circle"></i><div><strong>Sync needs attention</strong><span>{{ $agent->last_error }}</span></div></div>@endif
                    <form method="post" action="{{ route('attendance-devices.devices.update',$agent) }}" class="device-settings-form">@csrf @method('PUT')
                        <div class="device-settings-section"><h5>Assignment</h5><div class="row"><div class="col-sm-6 form-group"><label>Branch</label><select class="form-control" name="branch_id">@foreach($branches as $b)<option value="{{ $b->id }}" @selected($agent->branch_id==$b->id)>{{ $b->name }}</option>@endforeach</select></div><div class="col-sm-6 form-group"><label>Device name</label><input class="form-control" name="name" value="{{ $agent->name }}" required></div></div></div>
                        <div class="device-settings-section"><h5>Connection</h5><div class="row"><div class="col-sm-6 form-group"><label>LAN IP address</label><input class="form-control" name="device_ip" value="{{ $agent->device_ip }}" required></div><div class="col-sm-3 form-group"><label>Port</label><input class="form-control" type="number" name="device_port" value="{{ $agent->device_port ?: 4370 }}" required></div><div class="col-sm-3 form-group"><label>Timeout</label><div class="input-group"><input class="form-control" type="number" name="device_timeout" value="{{ $agent->device_timeout ?: 5 }}" min="1" max="60" required><span class="input-group-addon">sec</span></div></div></div><div class="row"><div class="col-sm-6 form-group"><label>Device identifier</label><input class="form-control" name="device_identifier" value="{{ $agent->device_identifier }}" required></div><div class="col-sm-6 form-group"><label>Timezone</label><input class="form-control" name="device_timezone" value="{{ $agent->device_timezone ?: 'Asia/Karachi' }}" required></div></div></div>
                        <div class="device-card-footer"><label class="device-toggle"><input type="checkbox" name="is_active" value="1" @checked($agent->is_active)><span>Device active</span></label><div class="device-card-buttons"><button class="btn btn-sm btn-primary" type="submit"><i class="fa fa-check"></i> Save changes</button></div></div>
                    </form>
                    <form method="post" action="{{ route('attendance-devices.devices.rotate-token',$agent) }}" class="device-credential-form">@csrf<button class="btn btn-link btn-sm" type="submit" onclick="return confirm('Rotate this Local Agent credential? The existing agent will stop authenticating until reprovisioned.')"><i class="fa fa-refresh"></i> Rotate Local Agent credential</button></form>
                </article>
                @endforeach
            @empty
            @endforelse
            @if($branches->sum(fn($branch) => $branch->attendanceSyncAgents->count()) === 0)
                <div class="device-empty"><i class="fa fa-clock-o"></i><strong>No attendance devices</strong><span>Add a device and assign it to a branch to start syncing attendance.</span></div>
            @endif
            </div>
        </section>
    </div>
</div>
</div>
@endsection
