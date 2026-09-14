<?php

namespace App\Console\Commands;

use App\Models\AttendanceSyncAgent;
use App\Models\Branch;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ProvisionAttendanceAgent extends Command
{
    protected $signature = 'attendance:agent-provision {name} {device_identifier} {--branch=main : Company branch code}';
    protected $description = 'Create or rotate a branch-bound Local Attendance Sync Agent credential';

    public function handle(): int
    {
        $branch = Branch::where('code', $this->option('branch'))->where('is_active', true)->first();
        if (! $branch) {
            $this->error('Active branch not found: '.$this->option('branch'));
            return self::FAILURE;
        }

        $token = Str::random(64);
        $agent = AttendanceSyncAgent::updateOrCreate(
            ['device_identifier' => $this->argument('device_identifier')],
            ['branch_id' => $branch->id, 'name' => $this->argument('name'), 'token_hash' => hash('sha256', $token), 'is_active' => true, 'last_error' => null]
        );

        $this->warn('Copy this token now. It is not stored in plaintext and cannot be displayed again:');
        $this->line($token);
        $this->newLine();
        $this->info('Agent ID: '.$agent->id.' | Branch: '.$branch->name.' ('.$branch->code.') | Device: '.$agent->device_identifier);
        return self::SUCCESS;
    }
}
