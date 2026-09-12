<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\AttendanceSyncAgent;
use Illuminate\Http\JsonResponse;
class AttendanceAgentStatusController extends Controller {
 public function __invoke():JsonResponse {
  $agents=AttendanceSyncAgent::query()->orderBy('name')->get()->map(function($agent){$heartbeat=$agent->last_heartbeat_at;$online=$agent->is_active&&$heartbeat&&$heartbeat->greaterThan(now()->subMinutes(3));return ['id'=>$agent->id,'name'=>$agent->name,'device_identifier'=>$agent->device_identifier,'active'=>$agent->is_active,'online'=>(bool)$online,'last_heartbeat_at'=>$heartbeat?->toIso8601String(),'last_sync_at'=>$agent->last_sync_at?->toIso8601String(),'last_error'=>$agent->last_error];});
  return response()->json(['agents'=>$agents,'server_time'=>now()->toIso8601String()]);
 }
}
