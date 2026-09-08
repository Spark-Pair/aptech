<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceImporter
{
    /** Validate everything before writing; retain earliest IN and latest OUT on replay. */
    public function import(array $logs): array
    {
        $grouped = [];
        $skipped = 0;
        $employees = Employee::pluck('joining_date', 'empid');
        foreach ($logs as $index => $log) {
            $type = filter_var($log['type'] ?? null, FILTER_VALIDATE_INT);
            if (! in_array($type, [0, 1, 4, 5], true)) {
                $skipped++;

                continue;
            }
            try {
                if (empty($log['timestamp']) || ! is_string($log['timestamp'])) {
                    throw new \InvalidArgumentException;
                }
                $time = CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $log['timestamp']);
                if ($time->format('Y-m-d H:i:s') !== $log['timestamp'] || $time->isFuture()) {
                    throw new \InvalidArgumentException;
                }
            } catch (\Throwable $e) {
                throw ValidationException::withMessages(['file' => 'Invalid or future timestamp at log '.($index + 1).'. Use YYYY-MM-DD HH:MM:SS.']);
            }
            $id = $log['id'] ?? null;
            if (! $employees->has($id) || $time->toDateString() < substr((string) $employees[$id], 0, 10)) {
                $skipped++;

                continue;
            }
            $date = $time->toDateString();
            $field = in_array($type, [0, 4], true) ? 'check_in' : 'check_out';
            $previous = $grouped[$id][$date][$field] ?? null;
            if (! $previous || ($field === 'check_in' ? $time->lt($previous) : $time->gt($previous))) {
                $grouped[$id][$date][$field] = $time;
            }
        }
        $days = DB::transaction(function () use ($grouped) {
            $days = 0;
            foreach ($grouped as $id => $dates) {
                ksort($dates);
                // Serialize all attendance operations for an employee on databases supporting row locks.
                Employee::where('empid', $id)->lockForUpdate()->firstOrFail();
                foreach ($dates as $date => $fields) {
                    $row = Attendance::firstOrNew(['empid' => $id, 'date' => $date]);
                    foreach ($fields as $field => $time) {
                        if (! $row->$field || ($field === 'check_in' ? $time->lt($row->$field) : $time->gt($row->$field))) {
                            $row->$field = $time;
                        }
                    }
                    $row->status = 'Present';
                    if ($row->isDirty()) {
                        $row->save();
                        $days++;
                    }
                }
            }

            return $days;
        });

        return ['days' => $days, 'skipped' => $skipped];
    }
}
