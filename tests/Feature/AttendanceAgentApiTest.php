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
