<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSyncAgent;
use App\Models\Employee;
use App\Models\User;
use App\Services\ZKTecoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class HrPortalTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        return $user;
    }

    private function employee(array $overrides = []): Employee
    {
        return Employee::create(array_merge([
            'empid' => 101, 'name' => 'Test Employee', 'email' => 'test@example.com', 'username' => 'test101',
            'password' => Hash::make('secret123'), 'designation' => 'Teacher', 'department' => 'Education',
            'joining_date' => '2026-01-01', 'salary' => 50000, 'is_active' => true,
        ], $overrides));
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

    public function test_attendance_agent_status_requires_auth_and_operations_exposes_polling_hook(): void
    {
        $this->get('/attendance-agent/status')->assertRedirect(route('login'));
        $this->signIn();
        $this->get('/operations')->assertOk()
            ->assertSee('data-attendance-agent-status', false)
            ->assertSee('data-status-url="'.route('attendance-agent.status').'"', false);
    }

    public function test_attendance_agent_status_reports_online_offline_health_without_exposing_token(): void
    {
        Carbon::setTestNow('2026-09-12 12:00:00');
        $this->signIn();
        AttendanceSyncAgent::create([
            'name' => 'Office Agent', 'device_identifier' => 'zk-office-1', 'token_hash' => hash('sha256', 'secret-token'),
            'is_active' => true, 'last_heartbeat_at' => now()->subMinute(), 'last_sync_at' => now()->subMinutes(2), 'last_error' => null,
        ]);
        AttendanceSyncAgent::create([
            'name' => 'Old Agent', 'device_identifier' => 'zk-old-1', 'token_hash' => hash('sha256', 'old-secret'),
            'is_active' => true, 'last_heartbeat_at' => now()->subMinutes(4), 'last_error' => 'Device unreachable',
        ]);

        $response = $this->getJson('/attendance-agent/status')->assertOk()
            ->assertJsonPath('agents.0.name', 'Office Agent')
            ->assertJsonPath('agents.0.online', true)
            ->assertJsonPath('agents.1.name', 'Old Agent')
            ->assertJsonPath('agents.1.online', false)
            ->assertJsonPath('agents.1.last_error', 'Device unreachable')
            ->assertJsonPath('server_time', now()->toIso8601String());

        $this->assertStringNotContainsString('secret-token', $response->getContent());
        $this->assertStringNotContainsString('old-secret', $response->getContent());
        $this->assertStringNotContainsString('token_hash', $response->getContent());
        Carbon::setTestNow();
    }

    public function test_employee_validation_hashing_update_and_machine_identity(): void
    {
        $this->signIn();
        $response = $this->post('/employees', [
            'empid' => 202, 'name' => 'Second Employee', 'email' => 'second@example.com', 'username' => 'second202',
            'password' => 'password123', 'designation' => 'Operator', 'department' => 'Production',
            'joining_date' => '2026-01-01', 'salary' => 40000, 'is_active' => 1,
        ]);
        $employee = Employee::where('empid', 202)->firstOrFail();
        $response->assertRedirect(route('employees.index', ['employee' => $employee->id]));
        $this->assertTrue(Hash::check('password123', $employee->password));
        $oldHash = $employee->password;
        $this->put("/employees/$employee->id", [
            'name' => 'Updated Employee', 'email' => 'second@example.com', 'username' => 'second202', 'password' => '',
            'designation' => 'Senior Operator', 'department' => 'Production', 'joining_date' => '2026-01-01', 'salary' => 45000, 'is_active' => 1,
        ])->assertRedirect();
        $employee->refresh();
        $this->assertSame('Updated Employee', $employee->name);
        $this->assertSame($oldHash, $employee->password);
        $this->put("/employees/$employee->id", [
            'empid' => 999, 'name' => 'Updated Employee', 'username' => 'second202', 'designation' => 'Senior Operator',
            'department' => 'Production', 'joining_date' => '2026-01-01', 'salary' => 45000, 'is_active' => 1,
        ])->assertSessionHasErrors('empid');
        $this->assertSame(202, $employee->fresh()->empid);
    }

    public function test_month_filter_uses_all_attendance_and_aggregates_without_n_plus_one(): void
    {
        $this->signIn();
        $this->employee();
        Attendance::create(['empid' => 101, 'date' => '2026-08-31', 'status' => 'Present']);
        Attendance::create(['empid' => 101, 'date' => '2026-09-01', 'status' => 'Present']);
        Attendance::create(['empid' => 101, 'date' => '2026-09-02', 'status' => 'Absent']);
        $this->get('/employees?month=2026-09')->assertOk()->assertViewHas('employees', function ($rows) {
            $employee = $rows[0];
            return $employee->attendance->count() === 2
                && $employee->present_days === 1
                && $employee->absent_days === 1
                && $employee->working_days === 2;
        });
    }

    public function test_early_and_late_minutes_and_monthly_totals(): void
    {
        $this->signIn();
        config(['attendance.shift_start' => '09:00', 'attendance.shift_end' => '17:00']);
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
            ->assertViewHas('selectedSummary', fn ($summary) => ($summary['Early Min'] ?? null) === 25 && ($summary['Late Min'] ?? null) === 12)
            ->assertSee('>25</span><div class="infobox-content">Early Min</div>', false)
            ->assertSee('>12</span><div class="infobox-content">Late Min</div>', false);
        config(['attendance.shift_start' => null, 'attendance.shift_end' => null]);
        $this->assertNull($late->early_minutes);
        $this->assertNull($late->late_minutes);
    }

    public function test_import_is_order_independent_and_repeat_safe(): void
    {
        $this->signIn(); $this->employee();
        $csv = "empid,timestamp,type\n101,2026-09-02 17:00:00,1\n101,2026-09-02 09:00:00,0\n101,2026-09-02 08:45:00,0\n101,2026-09-02 17:15:00,1\n";
        Storage::fake('local'); Storage::disk('local')->put('attendance.csv', $csv);
        $file = Storage::disk('local')->path('attendance.csv');
        $uploaded = new \Illuminate\Http\UploadedFile($file, 'attendance.csv', 'text/csv', null, true);
        $this->post('/operations/import', ['file' => $uploaded])->assertRedirect()->assertSessionHas('success');
        $row = Attendance::where(['empid'=>101,'date'=>'2026-09-02'])->firstOrFail();
        $this->assertSame('08:45', $row->check_in->format('H:i')); $this->assertSame('17:15', $row->check_out->format('H:i'));

        $uploadedReplay = new \Illuminate\Http\UploadedFile($file, 'attendance.csv', 'text/csv', null, true);
        $this->post('/operations/import', ['file' => $uploadedReplay])->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_csv_validation_is_atomic_and_rejects_invalid_dates(): void
    {
        $this->signIn(); $this->employee();
        Storage::fake('local'); Storage::disk('local')->put('bad.csv', "empid,timestamp,type\n101,2026-09-02 09:00:00,0\n101,not-a-date,1\n");
        $uploaded = new \Illuminate\Http\UploadedFile(Storage::disk('local')->path('bad.csv'), 'bad.csv', 'text/csv', null, true);
        $this->post('/operations/import', ['file'=>$uploaded])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_generation_preserves_records_excludes_today_and_before_joining(): void
    {
        $this->signIn(); Carbon::setTestNow('2026-09-08 12:00:00'); $this->employee(['joining_date'=>'2026-09-03']);
        Attendance::create(['empid'=>101,'date'=>'2026-09-04','status'=>'Present']);
        $this->post('/operations/generate',['month'=>'2026-09'])->assertRedirect();
        $this->assertDatabaseHas('attendances',['empid'=>101,'date'=>'2026-09-04','status'=>'Present']);
        $this->assertDatabaseMissing('attendances',['empid'=>101,'date'=>'2026-09-08']);
        $this->assertDatabaseMissing('attendances',['empid'=>101,'date'=>'2026-09-02']); Carbon::setTestNow();
    }

    public function test_leave_conflicts_roll_back_and_off_days_are_excluded(): void
    {
        $this->signIn(); $this->employee();
        Attendance::create(['empid'=>101,'date'=>'2026-09-02','status'=>'Present']);
        $this->post('/leaves',['empid'=>101,'start_date'=>'2026-09-01','end_date'=>'2026-09-03'])->assertSessionHasErrors();
        $this->assertDatabaseMissing('attendances',['empid'=>101,'date'=>'2026-09-01','status'=>'Leave']);
    }

    public function test_device_failure_is_recoverable_and_get_never_syncs(): void
    {
        $this->signIn();
        $mock=Mockery::mock(ZKTecoService::class);
        $mock->shouldReceive('connect')->once()->andReturn(false);
        $mock->shouldNotReceive('getAttendanceLogs');
        $mock->shouldNotReceive('disconnect');
        $this->app->instance(ZKTecoService::class,$mock);
        $this->post('/fetchLogs')->assertRedirect()->assertSessionHas('error');
        $this->get('/fetchLogs')->assertRedirect(route('operations.index'));
    }

    public function test_login_validation_and_logout(): void
    {
        $user=User::factory()->create(['username'=>'login-user','email'=>'login@example.com','password'=>Hash::make('password123')]);
        $this->post('/login',['username'=>'login-user','password'=>'wrong'])->assertSessionHasErrors('username');
        $this->post('/login',['username'=>'login-user','password'=>'password123'])->assertRedirect('/');
        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));
    }

    public function test_report_query_count_stays_constant_as_employees_grow(): void
    {
        $this->signIn();
        for($i=1;$i<=10;$i++) $this->employee(['empid'=>100+$i,'username'=>'u'.$i,'email'=>'u'.$i.'@example.com']);
        $queries=0; \DB::listen(function()use(&$queries){$queries++;}); $this->get('/employees?month=2026-09')->assertOk(); $this->assertLessThan(20,$queries);
    }

    public function test_successful_device_sync_disconnects_and_preserves_imported_punches(): void
    {
        $this->signIn(); $this->employee(); $mock=Mockery::mock(ZKTecoService::class); $mock->shouldReceive('connect')->once()->andReturn(true); $mock->shouldReceive('getAttendanceLogs')->once()->andReturn([['id'=>101,'timestamp'=>'2026-09-02 09:00:00','type'=>0],['id'=>101,'timestamp'=>'2026-09-02 17:00:00','type'=>1]]); $mock->shouldReceive('disconnect')->once(); $this->app->instance(ZKTecoService::class,$mock);
        $this->post('/fetchLogs')->assertRedirect(); $this->assertDatabaseHas('attendances',['empid'=>101,'date'=>'2026-09-02','status'=>'Present']);
    }
}
