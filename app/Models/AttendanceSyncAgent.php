<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSyncAgent extends Model
{
    use HasFactory;
    protected $fillable = ['branch_id','name','device_identifier','token_hash','is_active','last_heartbeat_at','last_sync_at','last_error'];
    protected $hidden = ['token_hash'];
    protected $casts = ['is_active'=>'boolean','last_heartbeat_at'=>'datetime','last_sync_at'=>'datetime'];

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function deviceUsers(): HasMany { return $this->hasMany(AttendanceDeviceUser::class); }
}
