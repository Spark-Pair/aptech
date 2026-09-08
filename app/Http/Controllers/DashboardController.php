<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportRequest;
use App\Models\Employee;
use App\Services\AttendanceReport;

class DashboardController extends Controller
{
    public function __invoke(ReportRequest $request, AttendanceReport $report)
    {
        $month = $request->month();
        $summary = $report->summary($report->attendance($month));
        $employeeCount = Employee::count();
        $activeCount = Employee::where('is_active', true)->count();
        $employees = $report->employees($month, $request->validated())->orderBy('name')->paginate(25)->withQueryString();

        return view('welcome', compact('month', 'summary', 'employeeCount', 'activeCount', 'employees'));
    }
}
