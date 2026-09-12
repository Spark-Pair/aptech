<?php

namespace App\Console\Commands;

use App\Models\AttendanceSyncAgent;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ProvisionAttendanceAgent extends Command
{
    protected $signature = 'attendance:agent-provision {name} {device_identifier}';
    protected $description = 'Create or rotate a Local Attendance Sync Agent credential';

    public function handle(): int
    {
        $token = Str::random(64);

        $agent = AttendanceSyncAgent::updateOrCreate(
            ['device_identifier' => $this->argument('device_identifier')],
            [
                'name' => $this->argument('name'),
                'token_hash' => hash('sha256', $token),
                'is_active' => true,
                'last_error' => null,
            ]
        );

        $this->warn('Copy this token now. It is not stored in plaintext and cannot be displayed again:');
        $this->line($token);
        $this->newLine();
        $this->info('Agent ID: '.$agent->id.' | Device: '.$agent->device_identifier);

        return self::SUCCESS;
    }
}
