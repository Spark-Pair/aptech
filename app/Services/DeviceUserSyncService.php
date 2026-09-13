<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DeviceUserSyncService
{
    public const DEFAULT_DESIGNATION = 'Employee';
    public const DEFAULT_DEPARTMENT = 'Unassigned';

    public function sync(array $users): array
    {
        return DB::transaction(function () use ($users) {
            $created = 0;
            $existing = 0;
            $skipped = 0;
            $seen = [];

            foreach ($users as $user) {
                $empid = filter_var($user['userid'] ?? null, FILTER_VALIDATE_INT);

                if ($empid === false || $empid < 1 || isset($seen[$empid])) {
                    $skipped++;

                    continue;
                }

                $seen[$empid] = true;

                if (Employee::where('empid', $empid)->exists()) {
                    $existing++;

                    continue;
                }

                try {
                    Employee::create([
                        'empid' => $empid,
                        'name' => $this->name($user['name'] ?? null, $empid),
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
                    $created++;
                } catch (QueryException $e) {
                    if (Employee::where('empid', $empid)->exists()) {
                        $existing++;

                        continue;
                    }

                    throw $e;
                }
            }

            return ['created' => $created, 'existing' => $existing, 'skipped' => $skipped];
        });
    }

    private function name(mixed $name, int $empid): string
    {
        $name = trim((string) $name);

        return $name !== '' ? $name : 'Device User '.$empid;
    }

    private function username(int $empid): string
    {
        $base = 'device_'.$empid;
        $username = $base;
        $suffix = 2;

        while (Employee::where('username', $username)->exists()) {
            $username = $base.'_'.$suffix;
            $suffix++;
        }

        return $username;
    }
}
