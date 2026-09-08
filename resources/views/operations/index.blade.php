@extends('layouts.app')
@section('title', 'Operations')
@section('subtitle', 'Manage machine data and attendance')
@section('content')
<div class="row">
<div class="col-md-6"><div class="widget-box" id="import"><div class="widget-header"><h4 class="widget-title"><i aria-hidden="true" class="fa fa-upload"></i> Upload Machine Data</h4></div><div class="widget-body"><div class="widget-main">
<p>Import a CSV file with columns <code>empid,timestamp,type</code>. Types: 0/4 = check-in; 1/5 = check-out. Maximum 5 MB and 20,000 punches.</p>
<pre>empid,timestamp,type
101,{{ today()->subDay()->format('Y-m-d') }} 09:00:00,0
101,{{ today()->subDay()->format('Y-m-d') }} 17:00:00,1</pre>
<p class="help-block">Use existing employee machine codes and local device time. Unknown employees are skipped. Re-importing retains the earliest check-in and latest check-out.</p>
<form method="post" action="{{ route('operations.import') }}" enctype="multipart/form-data">@csrf
<x-field name="file" label="Attendance CSV" type="file" accept=".csv,text/csv" required />
<button type="submit" class="btn btn-info"><i aria-hidden="true" class="fa fa-upload"></i> Import Attendance</button>
</form>
</div></div></div></div>
<div class="col-md-6"><div class="widget-box"><div class="widget-header"><h4 class="widget-title"><i aria-hidden="true" class="fa fa-exchange"></i> Fetch Data From Device</h4></div><div class="widget-body"><div class="widget-main">
<p>Sync attendance punches from the configured ZKTeco device.</p><p class="help-block">The device must be reachable on your network. Logs remain on the device after a successful sync.</p>
<form method="post" action="{{ route('attendance.sync') }}">@csrf<button type="submit" class="btn btn-success"><i aria-hidden="true" class="fa fa-refresh"></i> Fetch Attendance Logs</button></form>
</div></div></div>
<div class="widget-box" id="generate"><div class="widget-header"><h4 class="widget-title"><i aria-hidden="true" class="fa fa-calendar"></i> Generate Attendance</h4></div><div class="widget-body"><div class="widget-main">
<p>Fill missing days for active employees with Absent or Sunday Off Day. Only completed days on or after the joining date are included. Existing attendance and leave records are preserved.</p>
<form method="post" action="{{ route('operations.generate') }}">@csrf<x-field name="month" label="Month" type="month" :value="now()->format('Y-m')" :max="now()->format('Y-m')" required /><button type="submit" class="btn btn-warning"><i aria-hidden="true" class="fa fa-calendar-plus-o"></i> Generate Missing Days</button></form>
</div></div></div></div>
</div>
@endsection
