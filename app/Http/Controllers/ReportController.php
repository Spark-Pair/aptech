<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Shift;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    private function data(Request $request): array
    {
        $month = $request->input('month', now()->format('Y-m'));
        try { $start = CarbonImmutable::createFromFormat('!Y-m', $month); } catch (\Throwable $e) { $month=now()->format('Y-m'); $start=CarbonImmutable::createFromFormat('!Y-m',$month); }
        $end=$start->endOfMonth();
        $query=Attendance::query()->with('employee.shift')->whereBetween('date',[$start->toDateString(),$end->toDateString()])
            ->when($request->filled('employee'),fn($q)=>$q->where('empid',$request->input('employee')))
            ->when($request->filled('status'),fn($q)=>$q->where('status',$request->input('status')))
            ->when($request->filled('department'),fn($q)=>$q->whereHas('employee',fn($e)=>$e->where('department',$request->input('department'))))
            ->when($request->filled('shift'),fn($q)=>$q->whereHas('employee',fn($e)=>$e->where('shift_id',$request->input('shift'))));
        $records=(clone $query)->orderBy('date')->orderBy('empid')->get();
        $summary=['Present'=>$records->where('status','Present')->count(),'Absent'=>$records->where('status','Absent')->count(),'Off Day'=>$records->where('status','Off Day')->count(),'Leave'=>$records->where('status','Leave')->count()];
        $present=$records->where('status','Present');
        $totals=[
            'Total Records'=>$records->count(),
            'Employees'=>$records->pluck('empid')->unique()->count(),
            'Working Days'=>$records->where('status','!=','Off Day')->count(),
            'Late Min'=>$present->sum(fn($r)=>(int)($r->late_minutes??0)),
            'Early Min'=>$present->sum(fn($r)=>(int)($r->early_minutes??0)),
            'Attendance %'=>$records->whereNotIn('status',['Off Day'])->count() ? round(($summary['Present']/$records->whereNotIn('status',['Off Day'])->count())*100,1) : 0,
        ];
        $employeeRows=$records->groupBy('empid')->map(function($rows){ $employee=$rows->first()->employee; $working=$rows->where('status','!=','Off Day')->count(); $present=$rows->where('status','Present'); return (object)[
            'empid'=>$rows->first()->empid,'name'=>$employee?->name??'Unknown','department'=>$employee?->department??'—','shift'=>$employee?->shift?->name??'—',
            'present'=>$present->count(),'absent'=>$rows->where('status','Absent')->count(),'leave'=>$rows->where('status','Leave')->count(),'off'=>$rows->where('status','Off Day')->count(),
            'late'=>$present->sum(fn($r)=>(int)($r->late_minutes??0)),'early'=>$present->sum(fn($r)=>(int)($r->early_minutes??0)),'percentage'=>$working?round(($present->count()/$working)*100,1):0,
        ]; })->values();
        return compact('month','records','summary','totals','employeeRows') + [
            'employees'=>Employee::orderBy('name')->get(['empid','name']),
            'departments'=>Employee::whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
            'shifts'=>Shift::orderBy('name')->get(),
        ];
    }

    public function index(Request $request) { return view('reports.index',$this->data($request)); }

    public function csv(Request $request)
    {
        $data=$this->data($request); $records=$data['records']; $filename='attendance-report-'.$data['month'].'.csv';
        return response()->streamDownload(function() use($records){ $out=fopen('php://output','w'); fputcsv($out,['Date','Employee ID','Employee','Department','Shift','Status','Check In','Check Out','Late Min','Early Min']); foreach($records as $r) fputcsv($out,[$r->date?->format('Y-m-d'),$r->empid,$r->employee?->name,$r->employee?->department,$r->employee?->shift?->name,$r->status,$r->check_in?->format('H:i'),$r->check_out?->format('H:i'),$r->late_minutes??0,$r->early_minutes??0]); fclose($out); },$filename,['Content-Type'=>'text/csv']);
    }
}
