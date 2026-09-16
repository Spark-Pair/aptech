<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceAgentSyncRequest;
use App\Http\Requests\AttendanceAgentUsersRequest;
use App\Models\AttendanceSyncAgent;
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
        $credential = $request->attributes->get('attendance_sync_agent');
        $credential->forceFill(['last_heartbeat_at' => now(), 'last_error' => null])->save();

        $devices = $this->devicesForCredential($credential)
            ->with('branch:id,name,code')
            ->orderBy('name')
            ->get()
            ->map(fn (AttendanceSyncAgent $device) => [
                'id' => $device->id,
                'name' => $device->name,
                'branch_id' => $device->branch_id,
                'branch_name' => $device->branch?->name,
                'device_identifier' => $device->device_identifier,
                'device_ip' => $device->device_ip,
                'device_port' => $device->device_port,
                'device_timezone' => $device->device_timezone,
                'device_timeout' => $device->device_timeout,
            ])->values();

        return response()->json([
            'message' => 'Heartbeat accepted.',
            'server_time' => now()->toIso8601String(),
            'local_agent_key' => $credential->local_agent_key,
            'devices' => $devices,
            // Backward compatibility for older single-device Local Agents.
            'branch_id' => $credential->branch_id,
            'device' => $devices->first(),
        ]);
    }

    public function users(AttendanceAgentUsersRequest $request, DeviceUserSyncService $sync): JsonResponse
    {
        $credential = $request->attributes->get('attendance_sync_agent');
        $data = $request->validated();
        $agent = $this->resolveDevice($credential, $data['device_identifier']);
        if (! $agent) return response()->json(['message' => 'Device is not authorized for this Local Agent.'], 403);
        if (! $agent->branch_id) return response()->json(['message' => 'Attendance device is not assigned to a branch.'], 409);
        $result = $sync->sync($agent, $data['users']);
        $agent->forceFill(['last_heartbeat_at' => now(), 'last_error' => null])->save();
        return response()->json(['message' => 'Device users synchronized.', 'created' => $result['created'], 'existing' => $result['existing'], 'skipped' => $result['skipped'], 'branch_id' => $agent->branch_id]);
    }

    public function sync(AttendanceAgentSyncRequest $request, AttendanceImporter $importer, DeviceUserSyncService $deviceUsers): JsonResponse
    {
        $credential = $request->attributes->get('attendance_sync_agent');
        $data = $request->validated();
        $agent = $this->resolveDevice($credential, $data['device_identifier']);
        if (! $agent) return response()->json(['message' => 'Device is not authorized for this Local Agent.'], 403);
        if (! $agent->branch_id) return response()->json(['message' => 'Attendance device is not assigned to a branch.'], 409);

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

    private function devicesForCredential(AttendanceSyncAgent $credential)
    {
        return AttendanceSyncAgent::query()
            ->where('is_active', true)
            ->when(
                $credential->local_agent_key,
                fn ($q) => $q->where('local_agent_key', $credential->local_agent_key),
                fn ($q) => $q->whereKey($credential->id)
            );
    }

    private function resolveDevice(AttendanceSyncAgent $credential, string $identifier): ?AttendanceSyncAgent
    {
        return $this->devicesForCredential($credential)->where('device_identifier', $identifier)->first();
    }
}
