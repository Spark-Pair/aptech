<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSyncAgent;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttendanceDeviceController extends Controller
{
    public function index(): View
    {
        return view('attendance-devices.index', [
            'branches' => Branch::with(['attendanceSyncAgents' => fn ($q) => $q->orderBy('name')])->orderBy('name')->get(),
        ]);
    }

    public function storeBranch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'alpha_dash', 'max:50', 'unique:branches,code'],
        ]);
        Branch::create($data + ['is_active' => true]);
        return back()->with('success', 'Branch created.');
    }

    public function updateBranch(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'alpha_dash', 'max:50', Rule::unique('branches', 'code')->ignore($branch->id)],
            'is_active' => ['required', 'boolean'],
        ]);
        $branch->update($data);
        return back()->with('success', 'Branch updated.');
    }

    public function storeDevice(Request $request): RedirectResponse
    {
        $data = $this->validateDevice($request);
        $token = Str::random(64);
        AttendanceSyncAgent::create($data + [
            'token_hash' => hash('sha256', $token),
            'is_active' => true,
        ]);

        return back()->with('success', 'Device created.')->with('provision_token', $token);
    }

    public function updateDevice(Request $request, AttendanceSyncAgent $agent): RedirectResponse
    {
        $data = $this->validateDevice($request, $agent);
        $data['is_active'] = $request->boolean('is_active');
        $agent->update($data);
        return back()->with('success', 'Device configuration updated. The Local Agent will pick it up automatically on its next heartbeat.');
    }

    public function rotateToken(AttendanceSyncAgent $agent): RedirectResponse
    {
        $token = Str::random(64);
        $agent->update(['token_hash' => hash('sha256', $token), 'is_active' => true]);
        return back()->with('success', 'Agent credential rotated. Update only the local agent credential during provisioning.')->with('provision_token', $token);
    }

    private function validateDevice(Request $request, ?AttendanceSyncAgent $agent = null): array
    {
        return $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:120'],
            'device_identifier' => ['required', 'alpha_dash', 'max:120', Rule::unique('attendance_sync_agents', 'device_identifier')->ignore($agent?->id)],
            'device_ip' => ['required', 'ip'],
            'device_port' => ['required', 'integer', 'between:1,65535'],
            'device_timezone' => ['required', 'timezone'],
            'device_timeout' => ['required', 'integer', 'between:1,60'],
        ]);
    }
}
