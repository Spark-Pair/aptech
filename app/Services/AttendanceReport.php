<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class AttendanceReport
{
    public function range(string $month): array { $start=CarbonImmutable::createFromFormat('!Y-m',$month); return [$start->toDateString(),$start->endOfMonth()->toDateString()]; }
    public function employees(string $month,array $filters=[]): Builder
    {
        $range=$this->range($month);
        return Employee::query()->with('shift')->with(['attendance'=>fn($q)=>$q->whereBetween('date',$range)->select('id','empid','date','status','check_in','check_out')])
            ->when($filters['search']??null,fn($q,$v)=>$q->where(fn($q)=>$q->where('name','like','%'.$v.'%')->orWhere('empid',$v)))
            ->when($filters['department']??null,fn($q,$v)=>$q->where('department',$v))
            ->withCount([
                'attendance as working_days'=>fn($q)=>$q->whereBetween('date',$range)->where('status','!=','Off Day'),
                'attendance as present_days'=>fn($q)=>$q->whereBetween('date',$range)->where('status','Present'),
                'attendance as absent_days'=>fn($q)=>$q->whereBetween('date',$range)->where('status','Absent'),
                'attendance as off_days'=>fn($q)=>$q->whereBetween('date',$range)->where('status','Off Day'),
                'attendance as leave_days'=>fn($q)=>$q->whereBetween('date',$range)->where('status','Leave'),
            ]);
    }
    public function attendance(string $month,array $filters=[]): Builder
    {
        return Attendance::query()->with('employee.shift')->whereBetween('date',$this->range($month))
            ->when($filters['empid']??null,fn($q,$id)=>$q->where('empid',$id))
            ->when($filters['search']??null,fn($q,$name)=>$q->whereHas('employee',fn($e)=>$e->where('name','like','%'.$name.'%')))
            ->when($filters['status']??null,fn($q,$status)=>$q->where('status',$status));
    }
    public function summary(Builder $query): array
    {
        $counts=(clone $query)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total','status');
        return ['Present'=>(int)($counts['Present']??0),'Absent'=>(int)($counts['Absent']??0),'Off Day'=>(int)($counts['Off Day']??0),'Leave'=>(int)($counts['Leave']??0)];
    }
}
