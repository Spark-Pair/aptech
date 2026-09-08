@extends('layouts.app')
@section('title', 'Sign In')
@section('content')
<div class="position-relative"><div class="login-box visible widget-box no-border"><div class="widget-body"><div class="widget-main">
<h4 class="header blue lighter bigger"><i aria-hidden="true" class="ace-icon fa fa-coffee green"></i> Please Enter Your Information</h4>
<div class="space-6"></div>
<form method="post" action="{{ route('loginPost') }}">@csrf
<x-field name="username" label="Username" autocomplete="username" maxlength="255" autofocus required />
<x-field name="password" label="Password" type="password" autocomplete="current-password" required />
<div class="space"></div><button class="btn btn-primary btn-block" type="submit"><i aria-hidden="true" class="ace-icon fa fa-key"></i> Sign In</button>
</form>
</div><div class="toolbar center"><span class="white">Aptech HR &amp; Attendance Portal</span></div></div></div></div>
@endsection
