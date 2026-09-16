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
        $devices = AttendanceSyncAgent::with('branch:id,name,code')->orderByDesc('last_heartbeat_at')->orderBy('name')->get();
        $localAgents = $devices->groupBy(fn ($device) => $device->local_agent_key ?: 'agent-'.$device->id)
            ->map(fn ($group) => $group->first());

        return view('attendance-devices.index', [
            'branches' => Branch::with(['attendanceSyncAgents' => fn ($q) => $q->orderBy('name')])->orderBy('name')->get(),
            'localAgents' => $localAgents,
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
            $data['local_agent_key'] = $selected->local_agent_key ?: 'agent-'.$selected->id;
            $data['token_hash'] = $selected->token_hash;
            AttendanceSyncAgent::whereKey($selected->id)->whereNull('local_agent_key')->update(['local_agent_key' => $data['local_agent_key']]);
            AttendanceSyncAgent::create($data + ['is_active' => true]);
            return back()->with('success', 'Device added to the existing Local Agent. It will be picked up automatically on the next heartbeat.');
        }

        $token = Str::random(64);
        $data['local_agent_key'] = 'agent-'.Str::lower(Str::random(12));
        AttendanceSyncAgent::create($data + ['token_hash' => hash('sha256', $token), 'is_active' => true]);
        return back()->with('success', 'Device and Local Agent created.')->with('provision_token', $token);
    }

    public function updateDevice(Request $request, AttendanceSyncAgent $agent): RedirectResponse
    {
        $data = $this->validateDevice($request, $agent);
        $selected = $this->selectedLocalAgent($request);
        if ($selected && $selected->id !== $agent->id) {
            $data['local_agent_key'] = $selected->local_agent_key ?: 'agent-'.$selected->id;
            $data['token_hash'] = $selected->token_hash;
            AttendanceSyncAgent::whereKey($selected->id)->whereNull('local_agent_key')->update(['local_agent_key' => $data['local_agent_key']]);
        }
        $data['is_active'] = $request->boolean('is_active');
        $agent->update($data);
        return back()->with('success', 'Device configuration updated. The assigned Local Agent will pick it up automatically on its next heartbeat.');
    }

    public function rotateToken(AttendanceSyncAgent $agent): RedirectResponse
    {
        $token = Str::random(64);
        $key = $agent->local_agent_key ?: 'agent-'.$agent->id;
        $hash = hash('sha256', $token);
        AttendanceSyncAgent::where('local_agent_key', $key)->update(['token_hash' => $hash, 'is_active' => true]);
        if (! $agent->local_agent_key) $agent->update(['local_agent_key' => $key, 'token_hash' => $hash, 'is_active' => true]);
        return back()->with('success', 'Local Agent credential rotated for every device assigned to it. Reprovision that Local Agent once.')->with('provision_token', $token);
    }

    private function selectedLocalAgent(Request $request): ?AttendanceSyncAgent
    {
        $id = $request->input('local_agent_device_id');
        return $id ? AttendanceSyncAgent::findOrFail($id) : null;
    }

    private function validateDevice(Request $request, ?AttendanceSyncAgent $agent = null): array
    {
        $request->validate(['local_agent_device_id' => ['nullable', 'integer', 'exists:attendance_sync_agents,id']]);
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
