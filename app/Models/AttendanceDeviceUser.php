<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceDeviceUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_sync_agent_id',
        'employee_id',
        'device_user_id',
        'device_name',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AttendanceSyncAgent::class, 'attendance_sync_agent_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
