<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeRequest;
use App\Http\Requests\ReportRequest;
use App\Models\Employee;
use App\Services\AttendanceReport;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    public function index(ReportRequest $request, AttendanceReport $report)
    {
        $month = $request->month();
        $employees = $report->employees($month, $request->validated())->orderBy('name')->paginate(25)->withQueryString();
        $departments = Employee::distinct()->orderBy('department')->pluck('department');

        return view('employees.index', compact('month', 'employees', 'departments'));
    }

    public function create()
    {
        return view('employees.form', ['employee' => new Employee]);
    }

    public function edit(Employee $employee)
    {
        return view('employees.form', compact('employee'));
    }

    public function store(EmployeeRequest $request)
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        $employee = Employee::create($data);

        return redirect()->route('employees.show', $employee)->with('success', 'Employee created.');
    }

    public function update(EmployeeRequest $request, Employee $employee)
    {
        $data = $request->validated();
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        $employee->update($data);

        return redirect()->route('employees.show', $employee)->with('success', 'Employee updated.');
    }

    public function show(ReportRequest $request, Employee $employee, AttendanceReport $report)
    {
        $month = $request->month();
        $query = $report->attendance($month, ['empid' => $employee->empid]);
        $summary = $report->summary($query);
        $attendances = $query->orderBy('date')->get();

        return view('employees.show', compact('employee', 'month', 'summary', 'attendances'));
    }
}
