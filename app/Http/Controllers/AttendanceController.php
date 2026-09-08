<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportRequest;
use App\Services\AttendanceImporter;
use App\Services\AttendanceReport;
use App\Services\ZKTecoService;
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

    public function fetchLogs(ZKTecoService $device, AttendanceImporter $importer)
    {
        $lock = Cache::lock('attendance-device-sync', 180);
        if (! $lock->get()) {
            return back()->with('warning', 'A device sync is already running.');
        }
        $connected = false;
        try {
            $connected = $device->connect();
            if (! $connected) {
                return back()->with('error', 'Unable to connect to the device. Check its IP and network connection.');
            }
            $result = $importer->import($device->getAttendanceLogs());

            return back()->with('success', "{$result['days']} attendance days updated; {$result['skipped']} unsupported or unmatched logs skipped. Device logs retained.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Device sync failed. Check the device configuration, PHP sockets extension and application log.');
        } finally {
            try {
                if ($connected) {
                    $device->disconnect();
                }
            } catch (\Throwable $e) {
                report($e);
            } finally {
                $lock->release();
            }
        }
    }
}
