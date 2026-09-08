@extends('layouts.app')
@section('title', $employee->exists ? 'Edit Employee' : 'Add Employee')
@section('subtitle', 'Employee information')
@section('content')
<div class="widget-box"><div class="widget-header"><h4 class="widget-title"><i aria-hidden="true" class="fa fa-user"></i> Employee Details</h4></div><div class="widget-body"><div class="widget-main">
<form method="post" action="{{ $employee->exists ? route('employees.update', $employee) : route('employees.store') }}">
@csrf @if($employee->exists)@method('put')@endif
<div class="row">
    <div class="col-sm-6">@if($employee->exists)<div class="form-group"><label>Machine Code</label><p class="form-control-static">{{ $employee->empid }} <small class="text-muted">(linked to attendance history)</small></p></div>@else<x-field name="empid" label="Machine Code" type="number" min="1" max="2147483647" required />@endif</div>
    <div class="col-sm-6"><x-field name="name" label="Employee Name" :value="$employee->name" maxlength="255" required /></div>
    <div class="col-sm-6"><x-field name="email" label="Email" type="email" :value="$employee->email" maxlength="255" /></div>
    <div class="col-sm-6"><x-field name="username" label="Username" :value="$employee->username" maxlength="255" autocomplete="off" required /></div>
    <div class="col-sm-6"><x-field name="password" :label="$employee->exists ? 'New Password (leave blank to retain)' : 'Password'" type="password" minlength="8" autocomplete="new-password" :required="!$employee->exists" /></div>
    <div class="col-sm-6"><x-field name="designation" label="Designation" :value="$employee->designation" maxlength="255" required /></div>
    <div class="col-sm-6"><x-field name="department" label="Department" :value="$employee->department" maxlength="255" required /></div>
    <div class="col-sm-6"><x-field name="joining_date" label="Joining Date" type="date" :value="$employee->joining_date?->toDateString()" :max="today()->toDateString()" required /></div>
    <div class="col-sm-6"><x-field name="salary" label="Monthly Salary" type="number" :value="$employee->salary" min="0" max="99999999.99" step="0.01" required /></div>
    <div class="col-sm-6 form-group"><label for="is_active">Employment Status</label><select id="is_active" name="is_active" class="form-control"><option value="1" @selected(old('is_active', $employee->exists ? (int)$employee->is_active : 1) == 1)>Active</option><option value="0" @selected(old('is_active', $employee->exists ? (int)$employee->is_active : 1) == 0)>Inactive</option></select></div>
</div>
<div class="form-actions"><button type="submit" class="btn btn-info"><i aria-hidden="true" class="fa fa-check"></i> Save Employee</button> <a href="{{ route('employees.index') }}" class="btn btn-default">Cancel</a></div>
</form>
</div></div></div>
@endsection
