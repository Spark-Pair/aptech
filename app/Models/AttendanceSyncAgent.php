<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSyncAgent extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id', 'name', 'device_identifier', 'device_ip', 'device_port',
        'device_timezone', 'device_timeout', 'token_hash', 'is_active',
        'last_heartbeat_at', 'last_sync_at', 'last_error',
    ];
    protected $hidden = ['token_hash'];
    protected $casts = [
        'is_active' => 'boolean',
        'device_port' => 'integer',
        'device_timeout' => 'integer',
        'last_heartbeat_at' => 'datetime',
        'last_sync_at' => 'datetime',
    ];

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function deviceUsers(): HasMany { return $this->hasMany(AttendanceDeviceUser::class); }
}
