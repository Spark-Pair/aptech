<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    private const STATUSES = ['Present','Absent','Off Day','Leave'];

    private function data(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable','date'],
            'to' => ['nullable','date','after_or_equal:from'],
            'employee' => ['nullable','integer'],
            'statuses' => ['nullable','array'],
            'statuses.*' => ['string','in:Present,Absent,Off Day,Leave'],
        ]);

        $from = CarbonImmutable::parse($validated['from'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $to = CarbonImmutable::parse($validated['to'] ?? now()->endOfMonth()->toDateString())->endOfDay();
        $statuses = array_values(array_intersect(self::STATUSES, $validated['statuses'] ?? []));
        $employeeId = $validated['employee'] ?? null;

        $query = Attendance::query()->with('employee.shift')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($employeeId, fn($q) => $q->where('empid', $employeeId))
            ->when($statuses, fn($q) => $q->whereIn('status', $statuses));

        $records = (clone $query)->orderBy('date')->orderBy('empid')->get();
        $selectedEmployee = $employeeId ? Employee::with('shift')->where('empid', $employeeId)->first() : null;
        $summary = collect(self::STATUSES)->mapWithKeys(fn($status) => [$status => $records->where('status', $status)->count()])->all();
        $present = $records->where('status','Present');
        $working = $records->whereNotIn('status',['Off Day'])->count();
        $totals = [
            'Total Records' => $records->count(),
            'Employees' => $records->pluck('empid')->unique()->count(),
            'Working Days' => $working,
            'Late Min' => $present->sum(fn($r)=>(int)($r->late_minutes ?? 0)),
            'Early Min' => $present->sum(fn($r)=>(int)($r->early_minutes ?? 0)),
            'Attendance %' => $working ? round(($summary['Present'] / $working) * 100, 1) : 0,
        ];

        $employeeRows = $records->groupBy('empid')->map(function($rows) {
            $employee = $rows->first()->employee; $working = $rows->where('status','!=','Off Day')->count(); $present = $rows->where('status','Present');
            return (object)[
                'empid'=>$rows->first()->empid,'name'=>$employee?->name ?? 'Unknown','department'=>$employee?->department ?? '—','designation'=>$employee?->designation ?? '—','shift'=>$employee?->shift?->name ?? '—',
                'present'=>$present->count(),'absent'=>$rows->where('status','Absent')->count(),'leave'=>$rows->where('status','Leave')->count(),'off'=>$rows->where('status','Off Day')->count(),
                'late'=>$present->sum(fn($r)=>(int)($r->late_minutes ?? 0)),'early'=>$present->sum(fn($r)=>(int)($r->early_minutes ?? 0)),'percentage'=>$working ? round(($present->count()/$working)*100,1) : 0,
            ];
        })->values();

        return compact('from','to','statuses','records','selectedEmployee','summary','totals','employeeRows') + [
            'employees'=>Employee::with('shift')->where('is_active',true)->orderBy('name')->get(),
            'availableStatuses'=>self::STATUSES,
        ];
    }

    public function index(Request $request) { return view('reports.index', $this->data($request)); }

    public function csv(Request $request)
    {
        $data=$this->data($request); $records=$data['records']; $filename='attendance-report-'.$data['from']->format('Ymd').'-'.$data['to']->format('Ymd').'.csv';
        return response()->streamDownload(function() use($records){ $out=fopen('php://output','w'); fputcsv($out,['Date','Employee ID','Employee','Department','Designation','Shift','Status','Check In','Check Out','Late Min','Early Min']); foreach($records as $r) fputcsv($out,[$r->date?->format('Y-m-d'),$r->empid,$r->employee?->name,$r->employee?->department,$r->employee?->designation,$r->employee?->shift?->name,$r->status,$r->check_in?->format('H:i'),$r->check_out?->format('H:i'),$r->late_minutes??0,$r->early_minutes??0]); fclose($out); },$filename,['Content-Type'=>'text/csv; charset=UTF-8']);
    }

    public function excel(Request $request)
    {
        $data=$this->data($request); $records=$data['records'];
        $rows=[];
        $rows[]=['Attendance Report'];
        $rows[]=['Period',$data['from']->format('d M Y').' - '.$data['to']->format('d M Y')];
        $rows[]=['Employee',$data['selectedEmployee'] ? $data['selectedEmployee']->name.' ('.$data['selectedEmployee']->empid.')' : 'All Employees'];
        $rows[]=['Statuses',$data['statuses'] ? implode(', ',$data['statuses']) : 'All Statuses'];
        $rows[]=[];
        $rows[]=['Date','Employee ID','Employee','Department','Designation','Shift','Status','Check In','Check Out','Late Min','Early Min'];
        foreach($records as $r) $rows[]=[ $r->date?->format('Y-m-d'),$r->empid,$r->employee?->name,$r->employee?->department,$r->employee?->designation,$r->employee?->shift?->name,$r->status,$r->check_in?->format('H:i'),$r->check_out?->format('H:i'),$r->late_minutes??0,$r->early_minutes??0 ];
        $html='<html><head><meta charset="UTF-8"></head><body><table border="1">'; foreach($rows as $row){ $html.='<tr>'; foreach($row as $cell) $html.='<td>'.htmlspecialchars((string)$cell,ENT_QUOTES,'UTF-8').'</td>'; $html.='</tr>'; } $html.='</table></body></html>';
        $filename='attendance-report-'.$data['from']->format('Ymd').'-'.$data['to']->format('Ymd').'.xls';
        return response($html,200,['Content-Type'=>'application/vnd.ms-excel; charset=UTF-8','Content-Disposition'=>'attachment; filename="'.$filename.'"']);
    }
}
