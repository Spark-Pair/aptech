<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') | Aptech HR Portal</title>
    <link rel="stylesheet" href="{{ asset('hr/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('hr/font-awesome/4.5.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('hr/css/ace.min.css') }}">
    <link rel="stylesheet" href="{{ asset('hr/css/portal.css') }}">
    <script src="{{ asset('hr/js/ace-extra.min.js') }}"></script>
</head>
<body class="{{ auth()->check() ? 'no-skin' : 'login-layout light-login' }}">
@auth
<a class="sr-only sr-only-focusable" href="#main-content">Skip to content</a>
<div id="navbar" class="navbar navbar-default">
    <div class="navbar-container">
        <button type="button" class="navbar-toggle menu-toggler pull-left" id="menu-toggler" data-target="#sidebar" aria-label="Toggle navigation"><span class="icon-bar"></span><span class="icon-bar"></span><span class="icon-bar"></span></button>
        <div class="navbar-header pull-left"><a href="{{ route('dashboard') }}" class="navbar-brand"><small><i class="fa fa-leaf" aria-hidden="true"></i> Payroll System</small></a></div>
        <div class="navbar-buttons navbar-header pull-right"><ul class="nav ace-nav"><li class="light-blue">
            <a data-toggle="dropdown" href="#" class="dropdown-toggle" aria-label="Account menu" aria-haspopup="true"><i class="fa fa-user-circle-o" aria-hidden="true"></i> <span class="user-info"><small>Welcome,</small>{{ auth()->user()->name }}</span><i aria-hidden="true" class="ace-icon fa fa-caret-down"></i></a>
            <ul class="user-menu dropdown-menu-right dropdown-menu"><li><form method="post" action="{{ route('logout') }}" data-no-ajax>@csrf<button class="account-logout" type="submit"><i aria-hidden="true" class="fa fa-power-off"></i> Sign out</button></form></li></ul>
        </li></ul></div>
    </div>
</div>
<div class="main-container" id="main-container">
    @include('partials.sidebar')
    <div class="main-content"><div class="main-content-inner">
        <div class="breadcrumbs"><ul class="breadcrumb"><li><i aria-hidden="true" class="ace-icon fa fa-home home-icon"></i><a href="{{ route('dashboard') }}">Home</a></li><li class="active">@yield('title', 'Dashboard')</li></ul><span class="portal-date hidden-xs">{{ now()->format('D, d M Y') }}</span></div>
        <main class="page-content" id="main-content">
            <div class="page-header"><h1>@yield('title', 'Dashboard') <small><i aria-hidden="true" class="ace-icon fa fa-angle-double-right"></i> @yield('subtitle', 'Aptech HR Portal')</small></h1></div>
            @include('partials.messages')
            @yield('content')
        </main>
    </div></div>
    <div class="footer"><div class="footer-inner"><div class="footer-content"><span class="blue bolder">Aptech</span> HR &amp; Attendance Portal &copy; {{ now()->year }}</div></div></div>
</div>
@else
<main class="main-container"><div class="main-content"><div class="row"><div class="col-sm-10 col-sm-offset-1"><div class="login-container">
    <div class="center"><h1><i aria-hidden="true" class="ace-icon fa fa-leaf green"></i> <span class="blue">Aptech</span> <span class="grey">HR Portal</span></h1><h4 class="blue">Payroll &amp; Attendance</h4></div>
    @include('partials.messages')
    @yield('content')
</div></div></div></div></main>
@endauth
<script src="{{ asset('hr/js/jquery-2.1.4.min.js') }}"></script>
<script src="{{ asset('hr/js/bootstrap.min.js') }}"></script>
<script src="{{ asset('hr/js/ace.min.js') }}"></script>
<script src="{{ asset('hr/js/portal.js') }}"></script>
@stack('scripts')
</body>
</html>
