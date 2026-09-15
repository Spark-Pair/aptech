@extends('layouts.app')
@section('title', 'Operations')
@section('subtitle', 'Manage machine data and attendance')
@section('content')
<div class="row">
<div class="col-md-6">
<div class="widget-box" data-attendance-agent-status data-status-url="{{ route('attendance-agent.status') }}"><div class="widget-header"><h4 class="widget-title"><i class="fa fa-exchange"></i> Attendance Device Sync</h4></div><div class="widget-body"><div class="widget-main"><div data-agent-status-content><p class="help-block"><i class="fa fa-circle-o-notch fa-spin"></i> Checking Local Sync Agent status...</p></div><p class="help-block">Attendance syncs automatically through the Local Sync Agent. Refresh the attendance page to see newly saved records.</p></div></div></div>
</div>
<div class="col-md-6">
<div class="widget-box"><div class="widget-header"><h4 class="widget-title"><i class="fa fa-cogs"></i> Attendance Tools</h4></div><div class="widget-body"><div class="widget-main no-padding">
<div class="management-list-row" id="import"><div class="management-list-icon"><i class="fa fa-upload"></i></div><div class="management-list-content"><strong>Upload Machine Data</strong><small>Import attendance punches from a CSV file.</small></div><button type="button" class="btn btn-xs btn-white btn-info" data-toggle="modal" data-target="#import-modal">Open</button></div>
<div class="management-list-row" id="generate"><div class="management-list-icon"><i class="fa fa-calendar"></i></div><div class="management-list-content"><strong>Generate Attendance</strong><small>Fill missing completed days with Absent or Sunday Off Day.</small></div><button type="button" class="btn btn-xs btn-white btn-warning" data-toggle="modal" data-target="#generate-modal">Open</button></div>
<div class="management-list-row"><div class="management-list-icon"><i class="fa fa-refresh"></i></div><div class="management-list-content"><strong>Legacy Device Fetch</strong><small>Direct-device sync retained only during migration verification.</small></div><button type="button" class="btn btn-xs btn-white btn-success" data-toggle="modal" data-target="#legacy-sync-modal">Open</button></div>
</div></div></div>
</div>
</div>

<div class="modal fade" id="import-modal" tabindex="-1" role="dialog"><div class="modal-dialog" role="document"><div class="modal-content"><form method="post" action="{{ route('operations.import') }}" enctype="multipart/form-data">@csrf
<div class="modal-header"><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button><h4 class="modal-title">Upload Machine Data</h4></div><div class="modal-body"><p>Import a CSV with columns <code>empid,timestamp,type</code>. Types 0/4 are check-in and 1/5 are check-out. Maximum 5 MB and 20,000 punches.</p><pre>empid,timestamp,type
101,{{ today()->subDay()->format('Y-m-d') }} 09:00:00,0
101,{{ today()->subDay()->format('Y-m-d') }} 17:00:00,1</pre><x-field name="file" label="Attendance CSV" type="file" accept=".csv,text/csv" required /><p class="help-block">Unknown employees are skipped. Re-importing keeps the earliest check-in and latest check-out.</p></div><div class="modal-footer"><button type="button" class="btn btn-sm btn-default" data-dismiss="modal">Cancel</button><button class="btn btn-sm btn-info"><i class="fa fa-upload"></i> Import Attendance</button></div></form></div></div></div>

<div class="modal fade" id="generate-modal" tabindex="-1" role="dialog"><div class="modal-dialog modal-sm" role="document"><div class="modal-content"><form method="post" action="{{ route('operations.generate') }}">@csrf
<div class="modal-header"><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button><h4 class="modal-title">Generate Attendance</h4></div><div class="modal-body"><p class="help-block">Existing attendance and leave records are preserved.</p><x-field name="month" label="Month" type="month" :value="now()->format('Y-m')" :max="now()->format('Y-m')" required /></div><div class="modal-footer"><button type="button" class="btn btn-sm btn-default" data-dismiss="modal">Cancel</button><button class="btn btn-sm btn-warning"><i class="fa fa-calendar-plus-o"></i> Generate Missing Days</button></div></form></div></div></div>

<div class="modal fade" id="legacy-sync-modal" tabindex="-1" role="dialog"><div class="modal-dialog modal-sm" role="document"><div class="modal-content"><form method="post" action="{{ route('attendance.sync') }}">@csrf
<div class="modal-header"><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button><h4 class="modal-title">Legacy Device Fetch</h4></div><div class="modal-body"><div class="alert alert-warning" style="margin-bottom:0">Use this only during migration testing. Normal attendance now syncs automatically through the Local Sync Agent.</div></div><div class="modal-footer"><button type="button" class="btn btn-sm btn-default" data-dismiss="modal">Cancel</button><button class="btn btn-sm btn-success"><i class="fa fa-refresh"></i> Fetch Attendance Logs</button></div></form></div></div></div>
@endsection
