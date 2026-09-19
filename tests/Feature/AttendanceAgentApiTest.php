<?php
namespace Tests\Feature;
use App\Models\AttendanceDeviceUser;
use App\Models\AttendanceSyncAgent;
use App\Models\Branch;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
class AttendanceAgentApiTest extends TestCase {
 use RefreshDatabase;
 private string $token='test-token-that-is-long-enough-1234567890';
 private function branch(string $code='main'):Branch{return Branch::firstOrCreate(['code'=>$code],['name'=>$code==='main'?'Main Branch':ucfirst($code),'is_active'=>true]);}
 private function agent(?Branch $branch=null,string $device='zk-office-1',?string $token=null):AttendanceSyncAgent{$token=$token?:$this->token;return AttendanceSyncAgent::create(['branch_id'=>($branch?:$this->branch())->id,'name'=>'Test Agent '.$device,'device_identifier'=>$device,'token_hash'=>hash('sha256',$token),'is_active'=>true]);}
 private function employee(int $empid=101,string $name='Agent Test Employee'):Employee{return Employee::create(['empid'=>$empid,'name'=>$name,'username'=>'agent-test-'.$empid,'password'=>Hash::make('password123'),'designation'=>'Tester','department'=>'QA','joining_date'=>'2026-09-01','salary'=>50000,'is_active'=>true]);}
 private function map(AttendanceSyncAgent $agent,Employee $employee,string $deviceUserId):AttendanceDeviceUser{return AttendanceDeviceUser::create(['attendance_sync_agent_id'=>$agent->id,'employee_id'=>$employee->id,'device_user_id'=>$deviceUserId,'device_name'=>$employee->name]);}
 public function test_heartbeat_requires_bearer_token():void{$this->postJson('/api/v1/attendance-agent/heartbeat')->assertUnauthorized();}
 public function test_active_agent_can_send_heartbeat():void{$a=$this->agent();$this->withToken($this->token)->postJson('/api/v1/attendance-agent/heartbeat')->assertOk()->assertJson(['message'=>'Heartbeat accepted.','branch_id'=>$a->branch_id]);}
 public function test_inactive_agent_is_rejected():void{$a=$this->agent();$a->update(['is_active'=>false]);$this->withToken($this->token)->postJson('/api/v1/attendance-agent/heartbeat')->assertUnauthorized();}
 public function test_shared_local_agent_token_returns_all_assigned_devices():void{
  $branchA=$this->branch('branch-a'); $branchB=$this->branch('branch-b'); $hash=hash('sha256',$this->token);
  AttendanceSyncAgent::create(['branch_id'=>$branchA->id,'name'=>'Device A','device_identifier'=>'device-a','local_agent_key'=>'shared-agent','token_hash'=>$hash,'is_active'=>true]);
  AttendanceSyncAgent::create(['branch_id'=>$branchB->id,'name'=>'Device B','device_identifier'=>'device-b','local_agent_key'=>'shared-agent','token_hash'=>$hash,'is_active'=>true]);
  $response=$this->withToken($this->token)->postJson('/api/v1/attendance-agent/heartbeat')->assertOk()->assertJson(['local_agent_key'=>'shared-agent']);
  $this->assertSame(['device-a','device-b'],collect($response->json('devices'))->pluck('device_identifier')->sort()->values()->all());
 }
 public function test_shared_local_agent_cannot_submit_for_device_outside_group():void{
  $branch=$this->branch('branch-a'); $hash=hash('sha256',$this->token);
  AttendanceSyncAgent::create(['branch_id'=>$branch->id,'name'=>'Device A','device_identifier'=>'device-a','local_agent_key'=>'shared-agent','token_hash'=>$hash,'is_active'=>true]);
  $otherToken='other-token-that-is-long-enough-1234567890';
  AttendanceSyncAgent::create(['branch_id'=>$branch->id,'name'=>'Device B','device_identifier'=>'device-b','local_agent_key'=>'other-agent','token_hash'=>hash('sha256',$otherToken),'is_active'=>true]);
  $this->withToken($this->token)->postJson('/api/v1/attendance-agent/users',['device_identifier'=>'device-b','users'=>[]])->assertForbidden();
 }
 public function test_sync_rejects_wrong_device_identifier():void{$this->agent();$this->withToken($this->token)->postJson('/api/v1/attendance-agent/sync',['batch_id'=>'batch-1','device_identifier'=>'wrong','logs'=>[['id'=>1,'timestamp'=>'2026-09-10 09:00:00','type'=>0]]])->assertForbidden();}
 public function test_sync_validates_bounded_logs():void{$this->agent();$this->withToken($this->token)->postJson('/api/v1/attendance-agent/sync',['batch_id'=>'batch-1','device_identifier'=>'zk-office-1','logs'=>[]])->assertUnprocessable()->assertJsonValidationErrors(['logs']);}
 public function test_user_sync_requires_bearer_token():void{$this->postJson('/api/v1/attendance-agent/users',['device_identifier'=>'zk-office-1','users'=>[]])->assertUnauthorized();}
 public function test_user_sync_rejects_wrong_device_identifier():void{$this->agent();$this->withToken($this->token)->postJson('/api/v1/attendance-agent/users',['device_identifier'=>'wrong','users'=>[['userid'=>1,'name'=>'Hasan']]])->assertForbidden();}
 public function test_user_sync_validates_payload():void{$this->agent();$this->withToken($this->token)->postJson('/api/v1/attendance-agent/users',['device_identifier'=>'zk-office-1','users'=>[['userid'=>0,'name'=>str_repeat('x',256)]]])->assertUnprocessable()->assertJsonValidationErrors(['users.0.userid','users.0.name']);}
 public function test_user_sync_rejects_unassigned_agent():void{$agent=$this->agent();$agent->update(['branch_id'=>null]);$this->withToken($this->token)->postJson('/api/v1/attendance-agent/users',['device_identifier'=>'zk-office-1','users'=>[['userid'=>1,'name'=>'Hasan']]])->assertStatus(409)->assertJson(['message'=>'Attendance device is not assigned to a branch.']);$this->assertDatabaseCount('attendance_device_users',0);}
 public function test_user_sync_creates_missing_employee_and_mapping():void{
  $agent=$this->agent(); $this->travelTo(\Carbon\CarbonImmutable::parse('2026-09-13 14:46:15',config('app.timezone')));
  $this->withToken($this->token)->postJson('/api/v1/attendance-agent/users',['device_identifier'=>'zk-office-1','users'=>[['userid'=>1,'name'=>'Hasan']]])->assertOk()->assertJson(['created'=>1,'existing'=>0,'skipped'=>0,'branch_id'=>$agent->branch_id]);
  $employee=Employee::firstOrFail(); $this->assertSame('Hasan',$employee->name); $this->assertSame('device_1',$employee->username); $this->assertSame('Employee',$employee->designation); $this->assertSame('Unassigned',$employee->department); $this->assertSame('2026-09-13',$employee->joining_date->toDateString()); $this->assertSame('0.00',(string)$employee->salary); $this->assertTrue($employee->is_active); $this->assertNull($employee->shift_id); $this->assertFalse(Hash::check('zkteco-device-password',$employee->password));
  $this->assertDatabaseHas('attendance_device_users',['attendance_sync_agent_id'=>$agent->id,'employee_id'=>$employee->id,'device_user_id'=>'1','device_name'=>'Hasan']);
 }
 public function test_user_sync_is_idempotent_for_same_agent_user():void{
  $this->agent(); $payload=['device_identifier'=>'zk-office-1','users'=>[['userid'=>1,'name'=>'Hasan']]];
  $this->withToken($this->token)->postJson('/api/v1/attendance-agent/users',$payload)->assertOk()->assertJson(['created'=>1,'existing'=>0]);
  $this->withToken($this->token)->postJson('/api/v1/attendance-agent/users',$payload)->assertOk()->assertJson(['created'=>0,'existing'=>1]);
  $this->assertDatabaseCount('employees',1); $this->assertDatabaseCount('attendance_device_users',1);
 }
 public function test_user_sync_allocator_skips_empids_created_after_sequence_initialization():void{
  $this->agent(); $this->employee(1,'Manual Employee');
  $this->withToken($this->token)->postJson('/api/v1/attendance-agent/users',['device_identifier'=>'zk-office-1','users'=>[['userid'=>2,'name'=>'Device Employee']]])->assertOk()->assertJson(['created'=>1]);
  $this->assertDatabaseHas('employees',['empid'=>2,'name'=>'Device Employee']); $this->assertDatabaseHas('attendance_employee_sequences',['id'=>1,'next_empid'=>3]);
 }
 public function test_same_device_user_id_on_two_agents_does_not_merge_employees():void{
  $branchA=$this->branch('branch-a'); $branchB=$this->branch('branch-b');
  $agentA=$this->agent($branchA,'zk-a',$this->token); $tokenB='second-test-token-that-is-long-enough-987654321'; $agentB=$this->agent($branchB,'zk-b',$tokenB);
  $this->withToken($this->token)->postJson('/api/v1/attendance-agent/users',['device_identifier'=>'zk-a','users'=>[['userid'=>1,'name'=>'Hasan']]])->assertOk()->assertJson(['created'=>1]);
  $this->withToken($tokenB)->postJson('/api/v1/attendance-agent/users',['device_identifier'=>'zk-b','users'=>[['userid'=>1,'name'=>'Ahmed']]])->assertOk()->assertJson(['created'=>1]);
  $this->assertDatabaseCount('employees',2); $this->assertDatabaseCount('attendance_device_users',2);
  $mapA=AttendanceDeviceUser::where('attendance_sync_agent_id',$agentA->id)->where('device_user_id','1')->firstOrFail(); $mapB=AttendanceDeviceUser::where('attendance_sync_agent_id',$agentB->id)->where('device_user_id','1')->firstOrFail();
  $this->assertNotSame($mapA->employee_id,$mapB->employee_id); $this->assertSame('Hasan',$mapA->employee->name); $this->assertSame('Ahmed',$mapB->employee->name);
 }
 public function test_user_sync_does_not_overwrite_existing_employee_business_fields():void{
  $agent=$this->agent(); $employee=Employee::create(['empid'=>1,'name'=>'Manual Name','email'=>'manual@example.com','username'=>'manual1','password'=>Hash::make('manual-password'),'designation'=>'Manager','department'=>'Admin','joining_date'=>'2026-01-01','salary'=>50000,'is_active'=>false]); $oldHash=$employee->password;
  $this->withToken($this->token)->postJson('/api/v1/attendance-agent/users',['device_identifier'=>'zk-office-1','users'=>[['userid'=>1,'name'=>'Hasan']]])->assertOk()->assertJson(['created'=>0,'existing'=>1,'skipped'=>0]);
  $employee=$employee->fresh(); $this->assertSame('Manual Name',$employee->name); $this->assertSame('manual@example.com',$employee->email); $this->assertSame('manual1',$employee->username); $this->assertSame($oldHash,$employee->password); $this->assertSame('Manager',$employee->designation); $this->assertSame('Admin',$employee->department); $this->assertFalse($employee->is_active); $this->assertDatabaseHas('attendance_device_users',['attendance_sync_agent_id'=>$agent->id,'employee_id'=>$employee->id,'device_user_id'=>'1']);
 }
 public function test_user_sync_uses_safe_name_default_for_empty_name():void{$this->agent();$this->withToken($this->token)->postJson('/api/v1/attendance-agent/users',['device_identifier'=>'zk-office-1','users'=>[['userid'=>3,'name'=>'']]])->assertOk()->assertJson(['created'=>1]);$this->assertDatabaseHas('employees',['name'=>'Device User 3']);}
 public function test_sync_keeps_server_side_future_timestamp_validation_for_mapped_user():void{$agent=$this->agent();$employee=$this->employee();$this->map($agent,$employee,'101');$future=now()->addDay()->format('Y-m-d H:i:s');$this->withToken($this->token)->postJson('/api/v1/attendance-agent/sync',['batch_id'=>'future-batch-1','device_identifier'=>'zk-office-1','logs'=>[['id'=>101,'timestamp'=>$future,'type'=>0]]])->assertUnprocessable()->assertJsonValidationErrors(['file']);}
 public function test_sync_translates_device_user_to_company_employee_and_branch():void{
  $agent=$this->agent(); $employee=$this->employee(500,'Mapped Employee'); $this->map($agent,$employee,'7'); $this->travelTo(\Carbon\CarbonImmutable::parse('2026-09-13 14:46:15',config('app.timezone')));
  $this->withToken($this->token)->postJson('/api/v1/attendance-agent/sync',['batch_id'=>'mapped-batch-1','device_identifier'=>'zk-office-1','logs'=>[['id'=>7,'timestamp'=>'2026-09-13 14:18:42','type'=>5]]])->assertOk()->assertJson(['accepted'=>1,'skipped'=>0,'branch_id'=>$agent->branch_id]);
  $this->assertDatabaseHas('attendances',['branch_id'=>$agent->branch_id,'empid'=>500,'date'=>'2026-09-13','status'=>'Present']);
 }
 public function test_unmapped_device_user_returns_retryable_conflict_without_acknowledging_batch():void{
  $agent=$this->agent(); $this->employee(7,'Unrelated Employee');
  $this->withToken($this->token)->postJson('/api/v1/attendance-agent/sync',['batch_id'=>'unmapped-batch-1','device_identifier'=>'zk-office-1','logs'=>[['id'=>7,'timestamp'=>'2026-09-10 09:00:00','type'=>0]]])->assertStatus(409)->assertJson(['code'=>'device_users_not_synced','unmapped_user_ids'=>['7']]);
  $this->assertDatabaseCount('attendances',0); $this->assertDatabaseCount('attendance_sync_batches',0);
 }
 public function test_successful_batch_is_idempotent_on_replay():void{
  $agent=$this->agent(); $employee=$this->employee(); $this->map($agent,$employee,'101'); $payload=['batch_id'=>'stable-batch-1','device_identifier'=>'zk-office-1','logs'=>[['id'=>101,'timestamp'=>'2026-09-10 09:00:00','type'=>0],['id'=>101,'timestamp'=>'2026-09-10 17:00:00','type'=>1]]];
  $first=$this->withToken($this->token)->postJson('/api/v1/attendance-agent/sync',$payload)->assertOk()->assertJson(['duplicate'=>false]); $this->withToken($this->token)->postJson('/api/v1/attendance-agent/sync',$payload)->assertOk()->assertJson(['duplicate'=>true,'batch_id'=>'stable-batch-1','accepted'=>$first->json('accepted'),'skipped'=>$first->json('skipped')]);
  $this->assertDatabaseCount('attendance_sync_batches',1); $this->assertDatabaseHas('attendances',['branch_id'=>$agent->branch_id,'empid'=>101,'date'=>'2026-09-10','status'=>'Present']);
 }
}
