<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceSyncAgent extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'device_identifier',
        'token_hash',
        'is_active',
        'last_heartbeat_at',
        'last_sync_at',
        'last_error',
    ];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'is_active' => 'boolean',
        'last_heartbeat_at' => 'datetime',
        'last_sync_at' => 'datetime',
    ];
}
