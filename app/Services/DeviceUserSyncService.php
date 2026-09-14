<?php

namespace App\Services;

use App\Models\AttendanceDeviceUser;
use App\Models\AttendanceSyncAgent;
use App\Models\Employee;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DeviceUserSyncService
{
    public const DEFAULT_DESIGNATION = 'Employee';
    public const DEFAULT_DEPARTMENT = 'Unassigned';

    public function sync(AttendanceSyncAgent $agent, array $users): array
    {
        return DB::transaction(function () use ($agent, $users) {
            $created = 0; $existing = 0; $skipped = 0; $seen = [];
            foreach ($users as $user) {
                $deviceUserId = trim((string) ($user['userid'] ?? ''));
                $numericDeviceId = filter_var($deviceUserId, FILTER_VALIDATE_INT);
                if ($deviceUserId === '' || $numericDeviceId === false || $numericDeviceId < 1 || isset($seen[$deviceUserId])) { $skipped++; continue; }
                $seen[$deviceUserId] = true;

                $mapping = AttendanceDeviceUser::query()->where('attendance_sync_agent_id', $agent->id)->where('device_user_id', $deviceUserId)->first();
                if ($mapping) {
                    $mapping->update(['device_name' => $this->name($user['name'] ?? null, $deviceUserId)]);
                    $existing++;
                    continue;
                }

                $employee = $this->claimLegacyEmployee($deviceUserId);
                if (! $employee) { $employee = $this->createEmployee($user['name'] ?? null, $deviceUserId); $created++; }
                else { $existing++; }

                try {
                    AttendanceDeviceUser::create([
                        'attendance_sync_agent_id' => $agent->id,
                        'employee_id' => $employee->id,
                        'device_user_id' => $deviceUserId,
                        'device_name' => $this->name($user['name'] ?? null, $deviceUserId),
                    ]);
                } catch (QueryException $e) {
                    if (! AttendanceDeviceUser::query()->where('attendance_sync_agent_id', $agent->id)->where('device_user_id', $deviceUserId)->exists()) throw $e;
                }
            }
            return ['created' => $created, 'existing' => $existing, 'skipped' => $skipped];
        });
    }

    public function unmappedDeviceUserIds(AttendanceSyncAgent $agent, array $logs): array
    {
        $ids = collect($logs)->pluck('id')->filter(fn ($id) => $id !== null && $id !== '')->map(fn ($id) => (string) $id)->unique()->values();
        if ($ids->isEmpty()) return [];

        $mapped = AttendanceDeviceUser::query()
            ->where('attendance_sync_agent_id', $agent->id)
            ->whereIn('device_user_id', $ids)
            ->pluck('device_user_id')
            ->map(fn ($id) => (string) $id);

        return $ids->diff($mapped)->values()->all();
    }

    public function translateLogs(AttendanceSyncAgent $agent, array $logs): array
    {
        $ids = collect($logs)->pluck('id')->filter(fn ($id) => $id !== null && $id !== '')->map(fn ($id) => (string) $id)->unique()->values();
        $map = AttendanceDeviceUser::query()->where('attendance_sync_agent_id', $agent->id)->whereIn('device_user_id', $ids)->with('employee:id,empid')->get()->filter(fn ($mapping) => $mapping->employee)->keyBy('device_user_id');
        return collect($logs)->map(function (array $log) use ($map) {
            $mapping = $map->get((string) ($log['id'] ?? ''));
            if (! $mapping) return null;
            $log['id'] = (int) $mapping->employee->empid;
            return $log;
        })->filter()->values()->all();
    }

    private function claimLegacyEmployee(string $deviceUserId): ?Employee
    {
        if (AttendanceDeviceUser::query()->where('device_user_id', $deviceUserId)->exists()) return null;
        return Employee::query()->where('empid', (int) $deviceUserId)->first();
    }

    private function createEmployee(mixed $name, string $deviceUserId): Employee
    {
        $empid = max(1, ((int) Employee::max('empid')) + 1);
        return Employee::create([
            'empid' => $empid,
            'name' => $this->name($name, $deviceUserId),
            'email' => null,
            'username' => $this->username($empid),
            'password' => Hash::make(Str::random(48)),
            'designation' => self::DEFAULT_DESIGNATION,
            'department' => self::DEFAULT_DEPARTMENT,
            'shift_id' => null,
            'joining_date' => now(config('app.timezone'))->toDateString(),
            'salary' => 0,
            'is_active' => true,
        ]);
    }

    private function name(mixed $name, string $deviceUserId): string
    {
        $name = trim((string) $name);
        return $name !== '' ? $name : 'Device User '.$deviceUserId;
    }

    private function username(int $empid): string
    {
        $base = 'device_'.$empid; $username = $base; $suffix = 2;
        while (Employee::where('username', $username)->exists()) $username = $base.'_'.$suffix++;
        return $username;
    }
}
