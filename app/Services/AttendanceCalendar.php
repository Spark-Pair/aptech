<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceCalendar
{
    public function generate(string $month, AttendanceReport $report): int
    {
        [$start, $end] = $report->range($month);
        $end = min($end, today()->subDay()->toDateString());
        $count = 0;
        Employee::where('is_active', true)->orderBy('id')->chunkById(100, function ($employees) use ($start, $end, &$count) {
            foreach ($employees as $employee) {
                $count += DB::transaction(function () use ($employee, $start, $end) {
                    Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();
                    $rows = [];
                    for ($date = CarbonImmutable::parse(max($start, $employee->joining_date->toDateString())); $date->toDateString() <= $end; $date = $date->addDay()) {
                        $rows[] = ['empid' => $employee->empid, 'date' => $date->toDateString(),
                            'status' => $date->dayOfWeek === config('attendance.off_day') ? 'Off Day' : 'Absent',
                            'created_at' => now(), 'updated_at' => now()];
                    }

                    return $rows ? Attendance::insertOrIgnore($rows) : 0;
                });
            }
        });

        return $count;
    }

    public function leave(int $empid, string $from, string $to): void
    {
        DB::transaction(function () use ($empid, $from, $to) {
            $employee = Employee::where('empid', $empid)->lockForUpdate()->firstOrFail();
            if (! $employee->is_active || $from < $employee->joining_date->toDateString()) {
                throw ValidationException::withMessages(['empid' => 'Choose an active employee and dates on or after their joining date.']);
            }
            $query = Attendance::where('empid', $empid)->whereBetween('date', [$from, $to]);
            if ((clone $query)->where(fn ($q) => $q->whereNotNull('check_in')->orWhereNotNull('check_out')->orWhere('status', 'Present'))->exists()) {
                throw ValidationException::withMessages(['from' => 'This range contains attendance punches. Choose dates without recorded attendance.']);
            }
            for ($date = CarbonImmutable::parse($from); $date->toDateString() <= $to; $date = $date->addDay()) {
                if ($date->dayOfWeek !== config('attendance.off_day')) {
                    Attendance::updateOrCreate(['empid' => $empid, 'date' => $date->toDateString()], ['status' => 'Leave']);
                }
            }
        });
    }
}
