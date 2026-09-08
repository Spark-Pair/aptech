<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeRequest;
use App\Http\Requests\ReportRequest;
use App\Models\Employee;
use App\Models\Shift;
use App\Services\AttendanceReport;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    public function index(ReportRequest $request, AttendanceReport $report)
    {
        $month = $request->month();
        $employees = $report->employees($month, $request->validated())->orderBy('name')->paginate(25)->withQueryString();
        $departments = Employee::distinct()->orderBy('department')->pluck('department');
        $selectedEmployee = null; $selectedAttendances = collect(); $selectedSummary = null;
        if ($request->filled('employee')) {
            $selectedEmployee = Employee::with('shift')->whereKey($request->integer('employee'))->first();
            if ($selectedEmployee) {
                $attendanceQuery = $report->attendance($month, ['empid'=>$selectedEmployee->empid]);
                $selectedSummary = $report->summary(clone $attendanceQuery);
                $selectedAttendances = $attendanceQuery->with('employee.shift')->orderBy('date')->get();
                $selectedSummary['Total Records'] = $selectedAttendances->count();
                $selectedSummary['Working Days'] = $selectedAttendances->where('status','!=','Off Day')->count();
                $selectedSummary['Early Min'] = $selectedAttendances->sum(fn($attendance)=>(int)($attendance->early_minutes ?? 0));
                $selectedSummary['Late Min'] = $selectedAttendances->sum(fn($attendance)=>(int)($attendance->late_minutes ?? 0));
            }
        }
        return view('employees.index', compact('month','employees','departments','selectedEmployee','selectedAttendances','selectedSummary'));
    }

    public function create() { return view('employees.form', ['employee'=>new Employee,'shifts'=>Shift::where('is_active',true)->orderBy('name')->get()]); }
    public function edit(Employee $employee) { return view('employees.form', compact('employee') + ['shifts'=>Shift::where('is_active',true)->orWhere('id',$employee->shift_id)->orderBy('name')->get()]); }

    public function store(EmployeeRequest $request)
    {
        $data=$request->validated(); $data['password']=Hash::make($data['password']); Employee::create($data);
        return redirect()->route('employees.index',['employee'=>Employee::where('empid',$data['empid'])->value('id')])->with('success','Employee created.');
    }
    public function update(EmployeeRequest $request, Employee $employee)
    {
        $data=$request->validated(); if(!empty($data['password'])) $data['password']=Hash::make($data['password']); else unset($data['password']); $employee->update($data);
        return redirect()->route('employees.index',['employee'=>$employee->id])->with('success','Employee updated.');
    }
    public function show(ReportRequest $request, Employee $employee) { return redirect()->route('employees.index',['employee'=>$employee->id,'month'=>$request->month()]); }
}
