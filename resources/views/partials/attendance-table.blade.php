<div class="table-responsive">
<table class="table table-striped table-bordered table-hover">
<thead><tr><th>Date</th>@if($showBranch ?? true)<th>Branch</th>@endif @if($showEmployee ?? true)<th>Employee</th><th>Machine Code</th>@endif<th>Time In</th><th>Time Out</th><th>Status</th><th>Working Hours</th><th>Early Min</th><th>Late Min</th><th style="width:70px">Edit</th></tr></thead>
<tbody>@forelse($attendances as $attendance)<tr>
<td>{{ $attendance->date->format('d M Y, D') }}</td>
@if($showBranch ?? true)<td>{{ $attendance->branch?->name ?? 'Unassigned' }}</td>@endif
@if($showEmployee ?? true)<td>{{ $attendance->employee?->name ?? 'Unknown employee' }}</td><td>{{ $attendance->empid }}</td>@endif
<td>{{ $attendance->check_in?->format('h:i A') ?? '-' }}</td><td>{{ $attendance->check_out?->format('h:i A') ?? '-' }}</td><td><x-status :status="$attendance->status" /></td>
<td>@if($attendance->check_in && $attendance->check_out && $attendance->check_out->gte($attendance->check_in)){{ number_format($attendance->check_in->diffInMinutes($attendance->check_out)/60,2) }} hrs @elseif($attendance->check_in || $attendance->check_out)<span class="orange">Incomplete punches</span>@else &mdash; @endif</td>
<td>{{ $attendance->early_minutes ?? '—' }}</td><td>{{ $attendance->late_minutes ?? '—' }}</td>
<td><button type="button" class="btn btn-xs btn-info" data-toggle="modal" data-target="#attendance-edit-{{ $attendance->id }}"><i class="fa fa-pencil"></i> Edit</button></td>
</tr>
@empty<tr><td colspan="{{ 8 + (($showEmployee ?? true) ? 2 : 0) + (($showBranch ?? true) ? 1 : 0) }}" class="empty-state"><i class="fa fa-calendar"></i><p>No attendance records for this selection.</p></td></tr>@endforelse</tbody>
</table>
</div>
@if($attendances instanceof \Illuminate\Contracts\Pagination\Paginator)@include('partials.pagination',['records'=>$attendances])@endif

@foreach($attendances as $attendance)
<div class="modal fade" id="attendance-edit-{{ $attendance->id }}" tabindex="-1" role="dialog"><div class="modal-dialog modal-sm" role="document"><div class="modal-content">
<form method="post" action="{{ route('attendances.update',$attendance) }}">@csrf @method('put')
<div class="modal-header"><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button><h4 class="modal-title">Edit Attendance</h4></div>
<div class="modal-body"><p><strong>{{ $attendance->employee?->name ?? 'Employee '.$attendance->empid }}</strong><br><small class="text-muted">{{ $attendance->date->format('d M Y, D') }}</small></p><div class="form-group"><label>Time In</label><input class="form-control" type="time" name="check_in" value="{{ $attendance->check_in?->format('H:i') }}"></div><div class="form-group"><label>Time Out</label><input class="form-control" type="time" name="check_out" value="{{ $attendance->check_out?->format('H:i') }}"></div><div class="form-group"><label>Status</label><select class="form-control" name="status">@foreach(['Present','Absent','Off Day','Leave'] as $status)<option value="{{ $status }}" @selected($attendance->status===$status)>{{ $status }}</option>@endforeach</select></div></div>
<div class="modal-footer"><button type="button" class="btn btn-sm btn-default" data-dismiss="modal">Cancel</button><button class="btn btn-sm btn-info"><i class="fa fa-check"></i> Save Changes</button></div>
</form></div></div></div>
@endforeach