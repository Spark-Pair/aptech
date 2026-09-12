<?php

namespace Tests\Feature;

use App\Models\AttendanceSyncAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceAgentApiTest extends TestCase
{
    use RefreshDatabase;

    private function agent(string $token = 'test-token-that-is-long-enough-1234567890'): AttendanceSyncAgent
    {
        return AttendanceSyncAgent::create([
            'name' => 'Test Agent',
            'device_identifier' => 'zk-office-1',
            'token_hash' => hash('sha256', $token),
            'is_active' => true,
        ]);
    }

    public function test_heartbeat_requires_bearer_token(): void
    {
        $this->postJson('/api/v1/attendance-agent/heartbeat')->assertUnauthorized();
    }

    public function test_active_agent_can_send_heartbeat(): void
    {
        $token = 'test-token-that-is-long-enough-1234567890';
        $this->agent($token);

        $this->withToken($token)
            ->postJson('/api/v1/attendance-agent/heartbeat')
            ->assertOk()
            ->assertJson(['message' => 'Heartbeat accepted.']);
    }

    public function test_inactive_agent_is_rejected(): void
    {
        $token = 'test-token-that-is-long-enough-1234567890';
        $agent = $this->agent($token);
        $agent->update(['is_active' => false]);

        $this->withToken($token)
            ->postJson('/api/v1/attendance-agent/heartbeat')
            ->assertUnauthorized();
    }

    public function test_sync_rejects_wrong_device_identifier(): void
    {
        $token = 'test-token-that-is-long-enough-1234567890';
        $this->agent($token);

        $this->withToken($token)->postJson('/api/v1/attendance-agent/sync', [
            'batch_id' => 'batch-1',
            'device_identifier' => 'another-device',
            'logs' => [['id' => 1, 'timestamp' => '2026-09-10 09:00:00', 'type' => 0]],
        ])->assertForbidden();
    }

    public function test_sync_validates_bounded_logs(): void
    {
        $token = 'test-token-that-is-long-enough-1234567890';
        $this->agent($token);

        $this->withToken($token)->postJson('/api/v1/attendance-agent/sync', [
            'batch_id' => 'batch-1',
            'device_identifier' => 'zk-office-1',
            'logs' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors(['logs']);
    }
}
