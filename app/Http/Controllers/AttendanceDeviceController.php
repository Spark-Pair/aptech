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
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'code' => ['required', 'alpha_dash', 'max:50', 'unique:branches,code']]);
        Branch::create($data + ['is_active' => true]);
        return back()->with('success', 'Branch created.');
    }

    public function updateBranch(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'code' => ['required', 'alpha_dash', 'max:50', Rule::unique('branches', 'code')->ignore($branch->id)], 'is_active' => ['required', 'boolean']]);
        $branch->update($data);
        return back()->with('success', 'Branch updated.');
    }

    public function storeDevice(Request $request): RedirectResponse
    {
        $data = $this->validateDevice($request);
        $selected = $this->selectedLocalAgent($request);
        if ($selected) {
            $key = $selected->local_agent_key ?: 'agent-'.$selected->id;
            if (! $selected->local_agent_key) $selected->update(['local_agent_key' => $key]);
            AttendanceSyncAgent::create($data + ['local_agent_key' => $key, 'token_hash' => $selected->token_hash, 'is_active' => true]);
            return back()->with('success', 'Device added. The running Local Agent will pick it up automatically on its next heartbeat.');
        }

        $token = Str::random(64);
        AttendanceSyncAgent::create($data + ['local_agent_key' => 'agent-'.Str::lower(Str::random(12)), 'token_hash' => hash('sha256', $token), 'is_active' => true]);
        return back()->with('success', 'First device created. Provision the Local Agent once with the credential below.')->with('provision_token', $token);
    }

    public function updateDevice(Request $request, AttendanceSyncAgent $agent): RedirectResponse
    {
        $data = $this->validateDevice($request, $agent);
        $selected = $this->selectedLocalAgent($request);
        if ($selected && $selected->id !== $agent->id && ! $agent->last_heartbeat_at) {
            $key = $selected->local_agent_key ?: 'agent-'.$selected->id;
            if (! $selected->local_agent_key) $selected->update(['local_agent_key' => $key]);
            $data['local_agent_key'] = $key;
            $data['token_hash'] = $selected->token_hash;
        }
        $data['is_active'] = $request->boolean('is_active');
        $agent->update($data);
        return back()->with('success', 'Device configuration updated. The Local Agent will pick it up automatically on its next heartbeat.');
    }

    public function rotateToken(AttendanceSyncAgent $agent): RedirectResponse
    {
        $token = Str::random(64);
        $key = $agent->local_agent_key ?: 'agent-'.$agent->id;
        if (! $agent->local_agent_key) $agent->update(['local_agent_key' => $key]);
        AttendanceSyncAgent::where('local_agent_key', $key)->update(['token_hash' => hash('sha256', $token), 'is_active' => true]);
        return back()->with('success', 'Local Agent credential rotated for all of its assigned devices.')->with('provision_token', $token);
    }

    private function selectedLocalAgent(Request $request): ?AttendanceSyncAgent
    {
        $id = $request->input('local_agent_device_id');
        if ($id) return AttendanceSyncAgent::findOrFail($id);

        return AttendanceSyncAgent::query()
            ->where('is_active', true)
            ->whereNotNull('last_heartbeat_at')
            ->orderByDesc('last_heartbeat_at')
            ->first();
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
