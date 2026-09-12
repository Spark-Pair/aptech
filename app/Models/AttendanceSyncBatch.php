<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceSyncBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_sync_agent_id',
        'batch_id',
        'accepted_count',
        'skipped_count',
        'updated_days',
    ];
}
