<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceAgentSyncRequest;
use App\Http\Requests\AttendanceAgentUsersRequest;
use App\Models\AttendanceSyncBatch;
use App\Services\AttendanceImporter;
use App\Services\DeviceUserSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceSyncController extends Controller
{
    public function heartbeat(Request $request): JsonResponse
    {
        $agent = $request->attributes->get('attendance_sync_agent');
        $agent->forceFill(['last_heartbeat_at' => now(), 'last_error' => null])->save();

        return response()->json([
            'message' => 'Heartbeat accepted.',
            'server_time' => now()->toIso8601String(),
            'branch_id' => $agent->branch_id,
            'device' => [
                'device_identifier' => $agent->device_identifier,
                'device_ip' => $agent->device_ip,
                'device_port' => $agent->device_port,
                'device_timezone' => $agent->device_timezone,
                'device_timeout' => $agent->device_timeout,
            ],
        ]);
    }

    public function users(AttendanceAgentUsersRequest $request, DeviceUserSyncService $sync): JsonResponse
    {
        $agent = $request->attributes->get('attendance_sync_agent');
        $data = $request->validated();
        if (! hash_equals($agent->device_identifier, $data['device_identifier'])) return response()->json(['message' => 'Device is not authorized for this credential.'], 403);
        if (! $agent->branch_id) return response()->json(['message' => 'Attendance agent is not assigned to a branch.'], 409);
        $result = $sync->sync($agent, $data['users']);
        $agent->forceFill(['last_heartbeat_at' => now(), 'last_error' => null])->save();
        return response()->json(['message' => 'Device users synchronized.', 'created' => $result['created'], 'existing' => $result['existing'], 'skipped' => $result['skipped'], 'branch_id' => $agent->branch_id]);
    }

    public function sync(AttendanceAgentSyncRequest $request, AttendanceImporter $importer, DeviceUserSyncService $deviceUsers): JsonResponse
    {
        $agent = $request->attributes->get('attendance_sync_agent');
        $data = $request->validated();
        if (! hash_equals($agent->device_identifier, $data['device_identifier'])) return response()->json(['message' => 'Device is not authorized for this credential.'], 403);
        if (! $agent->branch_id) return response()->json(['message' => 'Attendance agent is not assigned to a branch.'], 409);

        $existing = AttendanceSyncBatch::where('attendance_sync_agent_id', $agent->id)->where('batch_id', $data['batch_id'])->first();
        if ($existing) return response()->json(['message' => 'Batch already processed.', 'duplicate' => true, 'batch_id' => $existing->batch_id, 'accepted' => $existing->accepted_count, 'skipped' => $existing->skipped_count, 'updated_days' => $existing->updated_days, 'branch_id' => $agent->branch_id]);

        $unmapped = $deviceUsers->unmappedDeviceUserIds($agent, $data['logs']);
        if ($unmapped !== []) {
            $agent->forceFill(['last_heartbeat_at' => now(), 'last_error' => 'Attendance sync is waiting for device user mappings.'])->save();
            return response()->json(['code' => 'device_users_not_synced', 'message' => 'Attendance batch is waiting for device user synchronization.', 'unmapped_user_ids' => $unmapped], 409);
        }

        try {
            $response = DB::transaction(function () use ($agent, $data, $importer, $deviceUsers) {
                $translated = $deviceUsers->translateLogs($agent, $data['logs']);
                $result = $importer->import($translated, $agent->branch_id, $agent->device_timezone ?: config('app.timezone', 'Asia/Karachi'));
                $batch = AttendanceSyncBatch::create(['attendance_sync_agent_id' => $agent->id, 'batch_id' => $data['batch_id'], 'accepted_count' => count($data['logs']) - $result['skipped'], 'skipped_count' => $result['skipped'], 'updated_days' => $result['days']]);
                $agent->forceFill(['last_heartbeat_at' => now(), 'last_sync_at' => now(), 'last_error' => null])->save();
                return $batch;
            });
            return response()->json(['message' => 'Attendance batch accepted.', 'duplicate' => false, 'batch_id' => $response->batch_id, 'accepted' => $response->accepted_count, 'skipped' => $response->skipped_count, 'updated_days' => $response->updated_days, 'branch_id' => $agent->branch_id]);
        } catch (\Throwable $e) {
            $agent->forceFill(['last_heartbeat_at' => now(), 'last_error' => 'Attendance sync failed.'])->save();
            throw $e;
        }
    }
}
