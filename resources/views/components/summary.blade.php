@props(['summary'])
<div class="row report-summary">
@foreach($summary as $label => $count)
<div class="col-xs-6 col-md-3"><div class="infobox infobox-{{ ['Present'=>'green','Absent'=>'red','Off Day'=>'grey','Leave'=>'orange'][$label] }}">
    <div class="infobox-icon"><i aria-hidden="true" class="ace-icon fa fa-{{ ['Present'=>'check','Absent'=>'times','Off Day'=>'coffee','Leave'=>'calendar-o'][$label] }}"></i></div>
    <div class="infobox-data"><span class="infobox-data-number">{{ number_format($count) }}</span><div class="infobox-content">{{ $label }} days</div></div>
</div></div>
@endforeach
</div>
