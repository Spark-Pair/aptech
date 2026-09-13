<?php
namespace Tests\Feature;
use App\Models\AttendanceSyncAgent;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
class AttendanceAgentApiTest extends TestCase {
 use RefreshDatabase;
 private string $token='test-token-that-is-long-enough-1234567890';
 private function agent():AttendanceSyncAgent{return AttendanceSyncAgent::create(['name'=>'Test Agent','device_identifier'=>'zk-office-1','token_hash'=>hash('sha256',$this->token),'is_active'=>true]);}
 private function employee():Employee{return Employee::create(['empid'=>101,'name'=>'Agent Test Employee','username'=>'agent-test-101','password'=>Hash::make('password123'),'designation'=>'Tester','department'=>'QA','joining_date'=>'2026-09-01','salary'=>50000,'is_active'=>true]);}
 public function test_heartbeat_requires_bearer_token():void{$this->postJson('/api/v1/attendance-agent/heartbeat')->assertUnauthorized();}
 public function test_active_agent_can_send_heartbeat():void{$this->agent();$this->withToken($this->token)->postJson('/api/v1/attendance-agent/heartbeat')->assertOk()->assertJson(['message'=>'Heartbeat accepted.']);}
 public function test_inactive_agent_is_rejected():void{$a=$this->agent();$a->update(['is_active'=>false]);$this->withToken($this->token)->postJson('/api/v1/attendance-agent/heartbeat')->assertUnauthorized();}
 public function test_sync_rejects_wrong_device_identifier():void{$this->agent();$this->withToken($this->token)->postJson('/api/v1/attendance-agent/sync',['batch_id'=>'batch-1','device_identifier'=>'wrong','logs'=>[['id'=>1,'timestamp'=>'2026-09-10 09:00:00','type'=>0]]])->assertForbidden();}
 public function test_sync_validates_bounded_logs():void{$this->agent();$this->withToken($this->token)->postJson('/api/v1/attendance-agent/sync',['batch_id'=>'batch-1','device_identifier'=>'zk-office-1','logs'=>[]])->assertUnprocessable()->assertJsonValidationErrors(['logs']);}
 public function test_user_sync_requires_bearer_token():void{$this->postJson('/api/v1/attendance-agent/users',['device_identifier'=>'zk-office-1','users'=>[]])->assertUnauthorized();}
 public function test_user_sync_rejects_wrong_device_identifier():void{$this->agent();$this->withToken($this->token)->postJson('/api/v1/attendance-agent/users',['device_identifier'=>'wrong','users'=>[['userid'=>1,'name'=>'Hasan']]])->assertForbidden();}
 public function test_user_sync_validates_payload():void{$this->agent();$this->withToken($this->token)->postJson('/api/v1/attendance-agent/users',['device_identifier'=>'zk-office-1','users'=>[['userid'=>0,'name'=>str_repeat('x',256)]]])->assertUnprocessable()->assertJsonValidationErrors(['users.0.userid','users.0.name']);}
 public function test_user_sync_creates_missing_employee_from_device_user():void{
  $this->agent();
  $this->travelTo(\Carbon\CarbonImmutable::parse('2026-09-13 14:46:15', config('app.timezone')));
  $this->withToken($this->token)->postJson('/api/v1/attendance-agent/users',['device_identifier'=>'zk-office-1','users'=>[['userid'=>1,'name'=>'Hasan']]])->assertOk()->assertJson(['created'=>1,'existing'=>0,'skipped'=>0]);
  $employee=Employee::where('empid',1)->firstOrFail();
  $this->assertSame('Hasan',$employee->name);
  $this->assertSame('device_1',$employee->username);
  $this->assertSame('Employee',$employee->designation);
  $this->assertSame('Unassigned',$employee->department);
  $this->assertSame('2026-09-13',$employee->joining_date->toDateString());
  $this->assertSame('0.00',(string)$employee->salary);
  $this->assertTrue($employee->is_active);
  $this->assertNull($employee->shift_id);
  $this->assertFalse(Hash::check('zkteco-device-password',$employee->password));
 }
 public function test_user_sync_is_idempotent_for_same_device_user():void{
  $this->agent();
  $payload=['device_identifier'=>'zk-office-1','users'=>[['userid'=>1,'name'=>'Hasan']]];
  $this->withToken($this->token)->postJson('/api/v1/attendance-agent/users',$payload)->assertOk()->assertJson(['created'=>1,'existing'=>0]);
  $this->withToken($this->token)->postJson('/api/v1/attendance-agent/users',$payload)->assertOk()->assertJson(['created'=>0,'existing'=>1]);
  $this->assertDatabaseCount('employees',1);
 }
 public function test_user_sync_does_not_overwrite_existing_employee_business_fields():void{
  $this->agent();
  $employee=Employee::create(['empid'=>1,'name'=>'Manual Name','email'=>'manual@example.com','username'=>'manual1','password'=>Hash::make('manual-password'),'designation'=>'Manager','department'=>'Admin','joining_date'=>'2026-01-01','salary'=>50000,'is_active'=>false]);
  $oldHash=$employee->password;
  $this->withToken($this->token)->postJson('/api/v1/attendance-agent/users',['device_identifier'=>'zk-office-1','users'=>[['userid'=>1,'name'=>'Hasan']]])->assertOk()->assertJson(['created'=>0,'existing'=>1,'skipped'=>0]);
  $employee=$employee->fresh();
  $this->assertSame('Manual Name',$employee->name);
  $this->assertSame('manual@example.com',$employee->email);
  $this->assertSame('manual1',$employee->username);
  $this->assertSame($oldHash,$employee->password);
  $this->assertSame('Manager',$employee->designation);
  $this->assertSame('Admin',$employee->department);
  $this->assertSame('2026-01-01',$employee->joining_date->toDateString());
  $this->assertSame('50000.00',(string)$employee->salary);
  $this->assertFalse($employee->is_active);
 }
 public function test_user_sync_creates_two_missing_employees():void{
  $this->agent();
  $this->withToken($this->token)->postJson('/api/v1/attendance-agent/users',['device_identifier'=>'zk-office-1','users'=>[['userid'=>1,'name'=>'Hasan'],['userid'=>2,'name'=>'Second']]])->assertOk()->assertJson(['created'=>2,'existing'=>0,'skipped'=>0]);
  $this->assertDatabaseHas('employees',['empid'=>1,'name'=>'Hasan']);
  $this->assertDatabaseHas('employees',['empid'=>2,'name'=>'Second']);
 }
 public function test_user_sync_uses_safe_name_default_for_empty_name():void{
  $this->agent();
  $this->withToken($this->token)->postJson('/api/v1/attendance-agent/users',['device_identifier'=>'zk-office-1','users'=>[['userid'=>3,'name'=>'']]])->assertOk()->assertJson(['created'=>1]);
  $this->assertDatabaseHas('employees',['empid'=>3,'name'=>'Device User 3']);
 }
 public function test_sync_keeps_server_side_future_timestamp_validation():void{$this->agent();$future=now()->addDay()->format('Y-m-d H:i:s');$this->withToken($this->token)->postJson('/api/v1/attendance-agent/sync',['batch_id'=>'future-batch-1','device_identifier'=>'zk-office-1','logs'=>[['id'=>101,'timestamp'=>$future,'type'=>0]]])->assertUnprocessable()->assertJsonValidationErrors(['file']);}
 public function test_sync_accepts_valid_local_wall_clock_timestamp_in_app_timezone():void{
  $this->agent();
  $employee=$this->employee();
  $this->travelTo(\Carbon\CarbonImmutable::parse('2026-09-13 14:46:15', config('app.timezone')));
  $this->withToken($this->token)->postJson('/api/v1/attendance-agent/sync',['batch_id'=>'local-time-batch-1','device_identifier'=>'zk-office-1','logs'=>[['id'=>(int)$employee->empid,'timestamp'=>'2026-09-13 14:18:42','type'=>5]]])->assertOk();
  $this->assertDatabaseHas('attendances',['empid'=>101,'date'=>'2026-09-13','status'=>'Present']);
 }
 public function test_successful_batch_is_idempotent_on_replay():void{
  $this->agent();
  $employee=$this->employee();
  $payload=['batch_id'=>'stable-batch-1','device_identifier'=>'zk-office-1','logs'=>[['id'=>(int)$employee->empid,'timestamp'=>'2026-09-10 09:00:00','type'=>0],['id'=>(int)$employee->empid,'timestamp'=>'2026-09-10 17:00:00','type'=>1]]];
  $first=$this->withToken($this->token)->postJson('/api/v1/attendance-agent/sync',$payload)->assertOk()->assertJson(['duplicate'=>false]);
  $this->withToken($this->token)->postJson('/api/v1/attendance-agent/sync',$payload)->assertOk()->assertJson(['duplicate'=>true,'batch_id'=>'stable-batch-1','accepted'=>$first->json('accepted'),'skipped'=>$first->json('skipped')]);
  $this->assertDatabaseCount('attendance_sync_batches',1);
  $this->assertDatabaseHas('attendances',['empid'=>101,'date'=>'2026-09-10','status'=>'Present']);
 }
}
