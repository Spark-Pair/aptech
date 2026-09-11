<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class September2026DemoSeeder extends Seeder
{
    public function run(): void
    {
        $shifts = [
            'Morning' => ['start_time' => '09:00:00', 'end_time' => '17:00:00'],
            'Evening' => ['start_time' => '14:00:00', 'end_time' => '22:00:00'],
            'Night' => ['start_time' => '22:00:00', 'end_time' => '06:00:00'],
        ];

        foreach ($shifts as $name => $times) {
            Shift::updateOrCreate(['name' => $name], $times + ['is_active' => true]);
        }

        $shiftIds = Shift::whereIn('name', array_keys($shifts))->pluck('id', 'name');

        $employees = [
            [1258, 'Ahsan Khan', 'Developer', 'IT', 'Morning', 65000],
            [2568, 'Asim Ali', 'HR Executive', 'HR', 'Morning', 55000],
            [9876, 'Tabish Ahmed', 'Accountant', 'Accounts', 'Morning', 60000],
            [3258, 'Karim Raza', 'Support Officer', 'Operations', 'Evening', 50000],
            [7412, 'Ali Hassan', 'Supervisor', 'Operations', 'Evening', 58000],
            [4187, 'Saad Ahmed', 'Designer', 'Creative', 'Morning', 62000],
            [5321, 'Hamza Iqbal', 'Coordinator', 'Administration', 'Morning', 52000],
            [6634, 'Usman Tariq', 'Security Officer', 'Security', 'Night', 48000],
        ];

        foreach ($employees as [$empid, $name, $designation, $department, $shift, $salary]) {
            Employee::updateOrCreate(
                ['empid' => $empid],
                [
                    'name' => $name,
                    'email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
                    'username' => 'demo'.$empid,
                    'password' => Hash::make('password'),
                    'designation' => $designation,
                    'department' => $department,
                    'shift_id' => $shiftIds[$shift],
                    'joining_date' => '2026-01-01',
                    'salary' => $salary,
                    'is_active' => true,
                ]
            );
        }

        $employeesById = Employee::whereIn('empid', array_column($employees, 0))->get()->keyBy('empid');

        for ($day = 1; $day <= 30; $day++) {
            $date = Carbon::create(2026, 9, $day);

            foreach ($employees as $index => [$empid, $name, $designation, $department, $shiftName]) {
                if ($date->isSunday()) {
                    Attendance::updateOrCreate(
                        ['empid' => $empid, 'date' => $date->toDateString()],
                        ['status' => 'Off Day', 'check_in' => null, 'check_out' => null]
                    );
                    continue;
                }

                // Deterministic demo absences so re-running the seeder produces the same dataset.
                if ((($day * 7) + ($index * 11)) % 29 < 3) {
                    Attendance::updateOrCreate(
                        ['empid' => $empid, 'date' => $date->toDateString()],
                        ['status' => 'Absent', 'check_in' => null, 'check_out' => null]
                    );
                    continue;
                }

                $shift = $shifts[$shiftName];
                $start = $date->copy()->setTimeFromTimeString($shift['start_time']);
                $end = $date->copy()->setTimeFromTimeString($shift['end_time']);
                if ($shiftName === 'Night') {
                    $end->addDay();
                }

                $checkInOffset = (($day * 3 + $index * 5) % 31) - 10; // -10 to +20 minutes
                $checkOutOffset = (($day * 5 + $index * 7) % 36) - 15; // -15 to +20 minutes

                Attendance::updateOrCreate(
                    ['empid' => $empid, 'date' => $date->toDateString()],
                    [
                        'status' => 'Present',
                        'check_in' => $start->copy()->addMinutes($checkInOffset),
                        'check_out' => $end->copy()->addMinutes($checkOutOffset),
                    ]
                );
            }
        }

        $this->command?->info('September 2026 demo data seeded: 3 shifts, 8 employees, 240 attendance records.');
    }
}
