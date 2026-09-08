<form method="get" class="well well-sm report-filters">
    <div><x-field name="search" label="Employee name / machine code" :value="request('search')" placeholder="Search employees" /></div>
    <div><x-field name="month" label="Month" type="month" :value="$month" required /></div>
    @isset($departments)<div class="form-group"><label for="department">Department</label><select id="department" name="department" class="form-control"><option value="">All departments</option>@foreach($departments as $department)<option @selected(request('department') === $department)>{{ $department }}</option>@endforeach</select></div>@endisset
    <div class="filter-actions"><button class="btn btn-info btn-sm" type="submit"><i aria-hidden="true" class="fa fa-search"></i> Search</button><a class="btn btn-default btn-sm" href="{{ url()->current() }}">Reset</a></div>
</form>
