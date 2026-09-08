<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use App\Services\AttendanceImporter;
use App\Services\ZKTecoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HrPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 9, 8)->startOfDay());
    }

    private function employee(array $overrides = []): Employee
    {
        return Employee::create(array_merge(['empid' => 101, 'name' => 'Test Employee', 'username' => 'employee101',
            'password' => Hash::make('password123'), 'designation' => 'Teacher', 'department' => 'Education',
            'joining_date' => '2026-08-01', 'salary' => 50000, 'is_active' => true], $overrides));
    }

    private function signIn(): User
    {
        $user = User::factory()->create(['username' => 'admin']);
        $this->actingAs($user);

        return $user;
    }

    public function test_all_portal_pages_render_with_real_data(): void
    {
        $this->signIn();
        $employee = $this->employee();
        Attendance::create(['empid' => 101, 'date' => '2026-09-01', 'status' => 'Leave']);
        foreach (['/', '/employees', '/employees/create', "/employees/$employee->id", "/employees/$employee->id/edit", '/attendances', '/operations', '/leaves'] as $url) {
            $this->get($url)->assertOk()->assertSee('Payroll System');
        }
        $this->get('/leaves')->assertSee('Test Employee')->assertSee('Machine Code');
        $this->get('/employees?month=2026-08')->assertOk()->assertSee('August 2026');
    }

    public function test_employee_validation_hashing_update_and_machine_identity(): void
    {
        $this->signIn();
        $data = ['empid' => 102, 'name' => 'New Person', 'username' => 'person102', 'password' => 'password123',
            'designation' => 'Trainer', 'department' => 'IT', 'joining_date' => '2026-08-03', 'salary' => 45000, 'is_active' => 1];
        $this->post('/employees', $data)->assertSessionHasNoErrors()->assertRedirect();
        $employee = Employee::where('empid', 102)->firstOrFail();
        $this->assertTrue(Hash::check('password123', $employee->password));
        $this->post('/employees', $data)->assertSessionHasErrors(['empid', 'username']);
        unset($data['empid']);
        $data['name'] = 'Updated Person';
        $data['password'] = '';
        $this->put("/employees/$employee->id", $data)->assertSessionHasNoErrors();
        $this->assertSame('Updated Person', $employee->fresh()->name);
        $this->assertTrue(Hash::check('password123', $employee->fresh()->password));
        $data['empid'] = 999;
        $this->put("/employees/$employee->id", $data)->assertSessionHasErrors('empid');
    }

    public function test_month_filter_uses_all_attendance_and_aggregates_without_n_plus_one(): void
    {
        $this->signIn();
        $this->employee();
        Attendance::create(['empid' => 101, 'date' => '2026-08-01', 'status' => 'Present']);
        Attendance::create(['empid' => 101, 'date' => '2026-09-01', 'status' => 'Absent']);
        $this->get('/employees?month=2026-09')->assertViewHas('employees', fn ($rows) => $rows[0]->present_days === 0 && $rows[0]->absent_days === 1);
        $this->get('/attendances?month=2026-08&search=Test')->assertViewHas('summary', fn ($summary) => $summary['Present'] === 1 && $summary['Absent'] === 0);
        $this->get('/employees?search=Nobody')->assertSee('No employees match');
        $this->get('/employees?month=invalid')->assertSessionHasErrors('month');
    }

    public function test_early_and_late_minutes_and_monthly_totals(): void
    {
        config(['attendance.shift_start' => '09:00', 'attendance.shift_end' => '17:00']);
        $this->signIn();
        $employee = $this->employee();
        $late = Attendance::create(['empid' => 101, 'date' => '2026-09-01', 'status' => 'Present',
            'check_in' => '2026-09-01 09:12:00', 'check_out' => '2026-09-01 16:35:00']);
        $this->assertSame(12, $late->late_minutes);
        $this->assertSame(25, $late->early_minutes);
        $onTime = Attendance::create(['empid' => 101, 'date' => '2026-09-02', 'status' => 'Present',
            'check_in' => '2026-09-02 08:45:00', 'check_out' => '2026-09-02 17:15:00']);
        $this->assertSame(0, $onTime->early_minutes);
        $this->assertSame(0, $onTime->late_minutes);
        Attendance::create(['empid' => 101, 'date' => '2026-08-31', 'status' => 'Present',
            'check_in' => '2026-08-31 10:00:00', 'check_out' => '2026-08-31 16:00:00']);
        $missing = Attendance::create(['empid' => 101, 'date' => '2026-09-03', 'status' => 'Absent']);
        $this->assertNull($missing->early_minutes);
        $this->assertNull($missing->late_minutes);
        $incomplete = new Attendance(['date' => '2026-09-04', 'status' => 'Present', 'check_in' => '2026-09-04 09:05:00']);
        $this->assertSame(5, $incomplete->late_minutes);
        $this->assertNull($incomplete->early_minutes);
        $this->get('/employees?month=2026-09')->assertOk()->assertSee('Early Min')->assertSee('Late Min')
            ->assertViewHas('employees', fn ($rows) => $rows[0]->attendance->sum('early_minutes') === 25 && $rows[0]->attendance->sum('late_minutes') === 12);
        $this->get("/employees/$employee->id?month=2026-09")->assertOk()->assertSee('Early Min')->assertSee('Late Min')
            ->assertSee('<td>25</td><td>12</td>', false);
        config(['attendance.shift_start' => null, 'attendance.shift_end' => null]);
        $this->assertNull($late->early_minutes);
        $this->assertNull($late->late_minutes);
    }

    public function test_import_is_order_independent_and_repeat_safe(): void
    {
        $this->employee();
        $logs = [
            ['id' => 101, 'timestamp' => '2026-09-01 17:00:00', 'type' => 1],
            ['id' => 101, 'timestamp' => '2026-09-01 10:00:00', 'type' => 0],
            ['id' => 101, 'timestamp' => '2026-09-01 09:00:00', 'type' => 4],
            ['id' => 101, 'timestamp' => '2026-09-01 18:00:00', 'type' => 5],
            ['id' => 999, 'timestamp' => '2026-09-01 09:00:00', 'type' => 0],
        ];
        $importer = app(AttendanceImporter::class);
        $this->assertSame(['days' => 1, 'skipped' => 1], $importer->import($logs));
        $this->assertSame(['days' => 0, 'skipped' => 1], $importer->import($logs));
        $row = Attendance::sole();
        $this->assertSame('09:00', $row->check_in->format('H:i'));
        $this->assertSame('18:00', $row->check_out->format('H:i'));
        $this->assertSame('Present', $row->status);
    }

    public function test_csv_validation_is_atomic_and_rejects_invalid_dates(): void
    {
        $this->signIn();
        $this->employee();
        $csv = "empid,timestamp,type\n101,2026-09-01 09:00:00,0\n101,2026-02-30 17:00:00,1\n";
        $this->post('/operations/import', ['file' => UploadedFile::fake()->createWithContent('logs.csv', $csv)])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('attendances', 0);
        $csv = "empid,timestamp,type\n101,2026-09-01 09:00:00,0\n101,2026-09-01 17:00:00,1\n";
        $this->post('/operations/import', ['file' => UploadedFile::fake()->createWithContent('logs.csv', $csv)])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_generation_preserves_records_excludes_today_and_before_joining(): void
    {
        $this->signIn();
        $this->employee(['joining_date' => '2026-09-03']);
        Attendance::create(['empid' => 101, 'date' => '2026-09-04', 'status' => 'Leave']);
        $this->post('/operations/generate', ['month' => '2026-09'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('attendances', 5);
        $this->assertDatabaseHas('attendances', ['date' => '2026-09-06', 'status' => 'Off Day']);
        $this->assertDatabaseHas('attendances', ['date' => '2026-09-04', 'status' => 'Leave']);
        $this->assertDatabaseMissing('attendances', ['date' => '2026-09-08']);
        $this->post('/operations/generate', ['month' => '2026-09'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('attendances', 5);
        $this->post('/operations/generate', ['month' => '2026-10'])->assertSessionHasErrors('month');
    }

    public function test_leave_conflicts_roll_back_and_off_days_are_excluded(): void
    {
        $this->signIn();
        $this->employee();
        Attendance::create(['empid' => 101, 'date' => '2026-09-04', 'status' => 'Present', 'check_in' => '2026-09-04 09:00:00']);
        $this->post('/leaves', ['empid' => 101, 'from' => '2026-09-03', 'to' => '2026-09-05'])->assertSessionHasErrors('from');
        $this->assertDatabaseCount('attendances', 1);
        $this->post('/leaves', ['empid' => 101, 'from' => '2026-09-05', 'to' => '2026-09-07'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('attendances', 3);
        $this->assertDatabaseMissing('attendances', ['date' => '2026-09-06']);
    }

    public function test_device_failure_is_recoverable_and_get_never_syncs(): void
    {
        $this->signIn();
        $this->mock(ZKTecoService::class, function ($mock) {
            $mock->shouldReceive('connect')->once()->andReturn(false);
            $mock->shouldNotReceive('getAttendanceLogs');
        });
        $this->get('/fetchLogs')->assertRedirect('/operations');
        $this->post('/fetchLogs')->assertSessionHas('error');
    }

    public function test_login_validation_and_logout(): void
    {
        User::factory()->create(['username' => 'admin', 'password' => Hash::make('password123')]);
        $this->get('/login')->assertOk()->assertSee('Please Enter Your Information');
        $this->post('/login', [])->assertSessionHasErrors(['username', 'password']);
        $this->post('/login', ['username' => 'admin', 'password' => 'wrong'])->assertSessionHasErrors('username');
        $this->post('/login', ['username' => 'admin', 'password' => 'password123'])->assertRedirect('/');
        $this->assertAuthenticated();
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_report_query_count_stays_constant_as_employees_grow(): void
    {
        $this->employee();
        $report = app(\App\Services\AttendanceReport::class);
        \Illuminate\Support\Facades\DB::enableQueryLog();
        $report->employees('2026-09')->get();
        $before = count(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();
        foreach (range(102, 110) as $id) {
            $this->employee(['empid' => $id, 'username' => 'employee'.$id]);
        }
        \Illuminate\Support\Facades\DB::flushQueryLog();
        \Illuminate\Support\Facades\DB::enableQueryLog();
        $rows = $report->employees('2026-09')->get();
        $this->assertCount(10, $rows);
        $this->assertSame($before, count(\Illuminate\Support\Facades\DB::getQueryLog()));
        \Illuminate\Support\Facades\DB::disableQueryLog();
    }

    public function test_successful_device_sync_disconnects_and_preserves_imported_punches(): void
    {
        $this->signIn();
        $this->employee();
        $this->mock(ZKTecoService::class, function ($mock) {
            $mock->shouldReceive('connect')->once()->andReturn(true);
            $mock->shouldReceive('getAttendanceLogs')->once()->andReturn([
                ['id' => 101, 'type' => 0, 'timestamp' => '2026-09-01 09:00:00'],
            ]);
            $mock->shouldReceive('disconnect')->once();
        });
        $this->post('/fetchLogs')->assertSessionHas('success');
        $this->assertDatabaseHas('attendances', ['empid' => 101, 'date' => '2026-09-01', 'status' => 'Present']);
        $lock = \Illuminate\Support\Facades\Cache::lock('attendance-device-sync', 1);
        $this->assertTrue($lock->get());
        $lock->release();
    }
}
