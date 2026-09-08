@extends('layouts.app')
@section('title','Reports')
@section('subtitle','Advanced attendance analytics and reporting')
@section('content')
<form method="get" class="well well-sm report-filters">
<x-field name="month" label="Month" type="month" :value="$month" required />
<div class="form-group"><label>Employee</label><select name="employee" class="form-control"><option value="">All employees</option>@foreach($employees as $e)<option value="{{ $e->empid }}" @selected(request('employee')==$e->empid)>{{ $e->name }} ({{ $e->empid }})</option>@endforeach</select></div>
<div class="form-group"><label>Department</label><select name="department" class="form-control"><option value="">All departments</option>@foreach($departments as $d)<option @selected(request('department')===$d)>{{ $d }}</option>@endforeach</select></div>
<div class="form-group"><label>Shift</label><select name="shift" class="form-control"><option value="">All shifts</option>@foreach($shifts as $s)<option value="{{ $s->id }}" @selected((string)request('shift')===(string)$s->id)>{{ $s->name }}</option>@endforeach</select></div>
<div class="form-group"><label>Status</label><select name="status" class="form-control"><option value="">All statuses</option>@foreach(['Present','Absent','Off Day','Leave'] as $s)<option @selected(request('status')===$s)>{{ $s }}</option>@endforeach</select></div>
<div class="filter-actions"><button class="btn btn-info btn-sm"><i class="fa fa-search"></i> Apply</button><a href="{{ route('reports.index') }}" class="btn btn-default btn-sm">Reset</a><a href="{{ route('reports.csv',request()->query()) }}" class="btn btn-success btn-sm"><i class="fa fa-download"></i> Export CSV</a><button type="button" onclick="window.print()" class="btn btn-primary btn-sm"><i class="fa fa-print"></i> Print</button></div>
</form>
<x-summary :summary="$summary" />
<div class="row report-summary">
@foreach(['Total Records'=>'list-alt','Employees'=>'users','Working Days'=>'briefcase','Late Min'=>'clock-o','Early Min'=>'sign-out','Attendance %'=>'line-chart'] as $label=>$icon)
<div class="col-xs-6 col-md-3"><div class="infobox infobox-blue"><div class="infobox-icon"><i class="ace-icon fa fa-{{ $icon }}"></i></div><div class="infobox-data"><span class="infobox-data-number">{{ number_format($totals[$label]??0, $label==='Attendance %'?1:0) }}{{ $label==='Attendance %'?'%':'' }}</span><div class="infobox-content">{{ $label }}</div></div></div></div>
@endforeach
</div>
<div class="table-header">Employee Monthly Summary <span class="pull-right">{{ number_format($employeeRows->count()) }} employees</span></div>
<div class="table-responsive"><table class="table table-striped table-bordered table-hover"><thead><tr><th>ID</th><th>Employee</th><th>Department</th><th>Shift</th><th>Present</th><th>Absent</th><th>Leave</th><th>Off</th><th>Late Min</th><th>Early Min</th><th>Attendance</th></tr></thead><tbody>@forelse($employeeRows as $r)<tr><td>{{ $r->empid }}</td><td>{{ $r->name }}</td><td>{{ $r->department }}</td><td>{{ $r->shift }}</td><td>{{ $r->present }}</td><td>{{ $r->absent }}</td><td>{{ $r->leave }}</td><td>{{ $r->off }}</td><td>{{ $r->late }}</td><td>{{ $r->early }}</td><td><strong>{{ number_format($r->percentage,1) }}%</strong></td></tr>@empty<tr><td colspan="11" class="text-center">No records found for these filters.</td></tr>@endforelse</tbody></table></div>
<div class="table-header">Detailed Attendance Records <span class="pull-right">{{ number_format($records->count()) }} records</span></div>
<div class="table-responsive"><table class="table table-striped table-bordered table-hover"><thead><tr><th>Date</th><th>ID</th><th>Employee</th><th>Department</th><th>Shift</th><th>Status</th><th>Check In</th><th>Check Out</th><th>Late Min</th><th>Early Min</th></tr></thead><tbody>@forelse($records as $r)<tr><td>{{ $r->date?->format('d M Y') }}</td><td>{{ $r->empid }}</td><td>{{ $r->employee?->name }}</td><td>{{ $r->employee?->department }}</td><td>{{ $r->employee?->shift?->name??'—' }}</td><td>{{ $r->status }}</td><td>{{ $r->check_in?->format('h:i A')??'—' }}</td><td>{{ $r->check_out?->format('h:i A')??'—' }}</td><td>{{ $r->late_minutes??0 }}</td><td>{{ $r->early_minutes??0 }}</td></tr>@empty<tr><td colspan="10" class="text-center">No attendance records found.</td></tr>@endforelse</tbody></table></div>
@endsection
