<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportRequest;
use App\Models\Employee;
use App\Services\AttendanceCalendar;
use App\Services\AttendanceCsvReader;
use App\Services\AttendanceImporter;
use App\Services\AttendanceReport;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OperationController extends Controller
{
    public function index()
    {
        return view('operations.index');
    }

    public function import(Request $request, AttendanceImporter $importer, AttendanceCsvReader $reader)
    {
        $request->validate(['file' => 'required|file|max:5120']);
        $logs = $reader->read($request->file('file'));
        $result = $importer->import($logs);

        $message = "{$result['days']} attendance days updated; {$result['skipped']} unmatched logs skipped.";
        if ($request->wantsJson()) return response()->json(['message' => $message]);
        return back()->with('success', $message);
    }

    public function generate(Request $request, AttendanceCalendar $calendar, AttendanceReport $report)
    {
        $data = $request->validate(['month' => 'required|date_format:Y-m|before_or_equal:'.now()->format('Y-m')]);
        $count = $calendar->generate($data['month'], $report);

        $message = "$count missing attendance days generated.";
        if ($request->wantsJson()) return response()->json(['message' => $message]);
        return back()->with('success', $message);
    }

    public function leaves(ReportRequest $request, AttendanceReport $report)
    {
        $month = $request->month();
        $employees = Employee::where('is_active', true)->orderBy('name')->get(['empid', 'name']);
        $attendances = $report->attendance($month, ['status' => 'Leave'])->with('employee')->orderByDesc('date')->paginate(50)->withQueryString();

        return view('operations.leaves', compact('month', 'employees', 'attendances'));
    }

    public function storeLeave(Request $request, AttendanceCalendar $calendar)
    {
        $data = $request->validate(['empid' => 'required|integer|exists:employees,empid',
            'from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from']);
        $from = \Carbon\CarbonImmutable::parse($data['from']);
        if ($from->diffInDays($data['to']) > 366) {
            throw ValidationException::withMessages(['to' => 'Select a range of at most one year.']);
        }
        $calendar->leave($data['empid'], $data['from'], $data['to']);

        if ($request->wantsJson()) return response()->json(['message' => 'Leave recorded. Weekly off days were excluded.', 'refresh' => true]);
        return back()->with('success', 'Leave recorded. Weekly off days were excluded.');
    }
}
