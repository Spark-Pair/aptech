<div class="table-responsive"><table class="table table-striped table-bordered table-hover">
<thead><tr><th>Date</th>@if($showEmployee ?? true)<th>Employee</th><th>Machine Code</th>@endif<th>Time In</th><th>Time Out</th><th>Status</th><th>Working Hours</th><th>Early Min</th><th>Late Min</th></tr></thead>
<tbody>@forelse($attendances as $attendance)<tr>
<td>{{ $attendance->date->format('d M Y, D') }}</td>
@if($showEmployee ?? true)<td>{{ $attendance->employee?->name ?? 'Unknown employee' }}</td><td>{{ $attendance->empid }}</td>@endif
<td>{{ $attendance->check_in?->format('h:i A') ?? '-' }}</td><td>{{ $attendance->check_out?->format('h:i A') ?? '-' }}</td>
<td><x-status :status="$attendance->status" /></td><td>@if($attendance->check_in && $attendance->check_out && $attendance->check_out->gte($attendance->check_in)){{ number_format($attendance->check_in->diffInMinutes($attendance->check_out)/60, 2) }} hrs @elseif($attendance->check_in || $attendance->check_out)<span class="orange">Incomplete punches</span>@else &mdash; @endif</td>
<td>{{ $attendance->early_minutes ?? '—' }}</td><td>{{ $attendance->late_minutes ?? '—' }}</td>
</tr>@empty<tr><td colspan="{{ ($showEmployee ?? true) ? 9 : 7 }}" class="empty-state"><i aria-hidden="true" class="fa fa-calendar"></i><p>No attendance records for this selection.</p></td></tr>@endforelse</tbody>
</table></div>
@if($attendances instanceof \Illuminate\Contracts\Pagination\Paginator)@include('partials.pagination', ['records'=>$attendances])@endif
