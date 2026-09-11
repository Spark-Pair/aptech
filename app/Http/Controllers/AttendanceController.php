<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportRequest;
use App\Models\Attendance;
use App\Services\AttendanceImporter;
use App\Services\AttendanceReport;
use App\Services\ZKTecoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AttendanceController extends Controller
{
    public function index(ReportRequest $request, AttendanceReport $report)
    {
        $month = $request->month();
        $query = $report->attendance($month, $request->validated());
        $summary = $report->summary($query);
        $attendances = $query->with('employee')->orderByDesc('date')->orderBy('empid')->paginate(50)->withQueryString();
        return view('attendances.index', compact('month', 'summary', 'attendances'));
    }

    public function update(Request $request, Attendance $attendance)
    {
        $data = $request->validate([
            'check_in' => 'nullable|date_format:H:i',
            'check_out' => 'nullable|date_format:H:i',
            'status' => 'required|in:Present,Absent,Off Day,Leave',
        ]);
        $date = $attendance->date->toDateString();
        $attendance->update([
            'check_in' => $data['check_in'] ? $date.' '.$data['check_in'].':00' : null,
            'check_out' => $data['check_out'] ? $date.' '.$data['check_out'].':00' : null,
            'status' => $data['status'],
        ]);
        if ($request->wantsJson()) return response()->json(['message' => 'Attendance record updated.', 'refresh' => true]);
        return back()->with('success', 'Attendance record updated.');
    }

    public function fetchLogs(ZKTecoService $device, AttendanceImporter $importer)
    {
        $lock = Cache::lock('attendance-device-sync', 180);
        if (! $lock->get()) {
            $message = 'A device sync is already running.';
            if (request()->wantsJson()) return response()->json(['message' => $message, 'level' => 'warning'], 409);
            return back()->with('warning', $message);
        }
        $connected = false;
        try {
            $connected = $device->connect();
            if (! $connected) {
                $message = 'Unable to connect to the device. Check its IP and network connection.';
                if (request()->wantsJson()) return response()->json(['message' => $message, 'level' => 'error'], 422);
                return back()->with('error', $message);
            }
            $result = $importer->import($device->getAttendanceLogs());
            $message = "{$result['days']} attendance days updated; {$result['skipped']} unsupported or unmatched logs skipped. Device logs retained.";
            if (request()->wantsJson()) return response()->json(['message' => $message]);
            return back()->with('success', $message);
        } catch (\Illuminate\Validation\ValidationException $e) { throw $e; }
        catch (\Throwable $e) {
            report($e);
            $message = 'Device sync failed. Check the device configuration, PHP sockets extension and application log.';
            if (request()->wantsJson()) return response()->json(['message' => $message, 'level' => 'error'], 500);
            return back()->with('error', $message);
        }
        finally { try { if ($connected) $device->disconnect(); } catch (\Throwable $e) { report($e); } finally { $lock->release(); } }
    }
}
