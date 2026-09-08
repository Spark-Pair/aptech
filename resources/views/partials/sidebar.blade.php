<nav id="sidebar" class="sidebar responsive" aria-label="Main navigation">
    <div class="sidebar-shortcuts"><div class="sidebar-shortcuts-large">
        <a class="btn btn-success" href="{{ route('dashboard') }}" aria-label="Dashboard"><i aria-hidden="true" class="ace-icon fa fa-signal"></i></a>
        <a class="btn btn-info" href="{{ route('employees.create') }}" aria-label="Add employee"><i aria-hidden="true" class="ace-icon fa fa-pencil"></i></a>
        <a class="btn btn-warning" href="{{ route('employees.index') }}" aria-label="Employees"><i aria-hidden="true" class="ace-icon fa fa-users"></i></a>
        <a class="btn btn-danger" href="{{ route('operations.index') }}" aria-label="Operations"><i aria-hidden="true" class="ace-icon fa fa-cogs"></i></a>
    </div></div>
    <ul class="nav nav-list">
        @foreach([['dashboard','tachometer','Dashboard'],['employees.index','users','Employee Records'],['attendances.index','calendar','Attendance']] as [$route,$icon,$label])
        <li class="{{ request()->routeIs($route === 'employees.index' ? 'employees.*' : $route) ? 'active' : '' }}"><a href="{{ route($route) }}"><i aria-hidden="true" class="menu-icon fa fa-{{ $icon }}"></i><span class="menu-text">{{ $label }}</span></a><b class="arrow"></b></li>
        @endforeach
        <li class="{{ request()->routeIs('operations.*','leaves.*') ? 'active open' : '' }}">
            <a href="#" class="dropdown-toggle"><i aria-hidden="true" class="menu-icon fa fa-desktop"></i><span class="menu-text">Operations</span><b class="arrow fa fa-angle-down"></b></a><b class="arrow"></b>
            <ul class="submenu">
                <li class="{{ request()->routeIs('leaves.*') ? 'active' : '' }}"><a href="{{ route('leaves.index') }}"><i aria-hidden="true" class="menu-icon fa fa-caret-right"></i>Employee Leaves</a></li>
                <li><a href="{{ route('operations.index') }}#generate"><i aria-hidden="true" class="menu-icon fa fa-caret-right"></i>Generate Attendance</a></li>
                <li><a href="{{ route('operations.index') }}#import"><i aria-hidden="true" class="menu-icon fa fa-caret-right"></i>Upload Machine Data</a></li>
            </ul>
        </li>
    </ul>
    <div class="sidebar-toggle sidebar-collapse" id="sidebar-collapse"><i aria-hidden="true" class="ace-icon fa fa-angle-double-left" data-icon1="ace-icon fa fa-angle-double-left" data-icon2="ace-icon fa fa-angle-double-right"></i></div>
</nav>
