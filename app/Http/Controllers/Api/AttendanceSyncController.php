<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceAgentSyncRequest;
use App\Models\AttendanceSyncBatch;
use App\Services\AttendanceImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceSyncController extends Controller
{
    public function heartbeat(Request $request): JsonResponse
    {
        $agent = $request->attributes->get('attendance_sync_agent');
        $agent->forceFill([
            'last_heartbeat_at' => now(),
            'last_error' => null,
        ])->save();

        return response()->json([
            'message' => 'Heartbeat accepted.',
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function sync(AttendanceAgentSyncRequest $request, AttendanceImporter $importer): JsonResponse
    {
        $agent = $request->attributes->get('attendance_sync_agent');
        $data = $request->validated();

        if (! hash_equals($agent->device_identifier, $data['device_identifier'])) {
            return response()->json(['message' => 'Device is not authorized for this credential.'], 403);
        }

        $existing = AttendanceSyncBatch::where('attendance_sync_agent_id', $agent->id)
            ->where('batch_id', $data['batch_id'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Batch already processed.',
                'duplicate' => true,
                'batch_id' => $existing->batch_id,
                'accepted' => $existing->accepted_count,
                'skipped' => $existing->skipped_count,
                'updated_days' => $existing->updated_days,
            ]);
        }

        try {
            $response = DB::transaction(function () use ($agent, $data, $importer) {
                $result = $importer->import($data['logs']);
                $batch = AttendanceSyncBatch::create([
                    'attendance_sync_agent_id' => $agent->id,
                    'batch_id' => $data['batch_id'],
                    'accepted_count' => count($data['logs']) - $result['skipped'],
                    'skipped_count' => $result['skipped'],
                    'updated_days' => $result['days'],
                ]);

                $agent->forceFill([
                    'last_heartbeat_at' => now(),
                    'last_sync_at' => now(),
                    'last_error' => null,
                ])->save();

                return $batch;
            });

            return response()->json([
                'message' => 'Attendance batch accepted.',
                'duplicate' => false,
                'batch_id' => $response->batch_id,
                'accepted' => $response->accepted_count,
                'skipped' => $response->skipped_count,
                'updated_days' => $response->updated_days,
            ]);
        } catch (\Throwable $e) {
            $agent->forceFill([
                'last_heartbeat_at' => now(),
                'last_error' => 'Attendance sync failed.',
            ])->save();
            throw $e;
        }
    }
}
