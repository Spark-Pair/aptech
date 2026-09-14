<?php

namespace App\Console\Commands;

use App\Models\Branch;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ManageAttendanceBranch extends Command
{
    protected $signature = 'attendance:branch {name} {--code= : Stable branch code}';
    protected $description = 'Create or update a company branch used by attendance devices';

    public function handle(): int
    {
        $name = trim((string) $this->argument('name'));
        $code = trim((string) ($this->option('code') ?: Str::slug($name)));
        if ($name === '' || $code === '') {
            $this->error('Branch name and code are required.');
            return self::FAILURE;
        }

        $branch = Branch::updateOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]);
        $this->info('Branch ready: '.$branch->name.' ('.$branch->code.') | ID '.$branch->id);
        return self::SUCCESS;
    }
}
