@extends('layouts.app')
@section('title', 'Attendance')
@section('subtitle', 'Daily employee attendance')
@section('content')
<form method="get" class="well well-sm report-filters">
<x-field name="search" label="Employee Name" :value="request('search')" placeholder="Search employee name" />
<x-field name="month" label="Month" type="month" :value="$month" required />
@if(request('empid'))<input type="hidden" name="empid" value="{{ request('empid') }}">@endif
<div class="form-group"><label for="status">Status</label><select id="status" name="status" class="form-control"><option value="">All statuses</option>@foreach(['Present','Absent','Off Day','Leave'] as $status)<option @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select></div>
<div class="filter-actions"><button class="btn btn-info btn-sm"><i aria-hidden="true" class="fa fa-search"></i> Search</button><a href="{{ route('attendances.index') }}" class="btn btn-default btn-sm">Reset</a></div>
</form>
<x-summary :summary="$summary" />
<div class="table-header">Attendance Results <span class="pull-right">{{ number_format($attendances->total()) }} records</span></div>
@include('partials.attendance-table')
@endsection
