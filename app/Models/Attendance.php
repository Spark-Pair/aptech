<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'empid',
        'date',
        'check_in',
        'check_out',
        'status',
    ];

    protected $casts = [
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'date' => 'date',
    ];

    public function setDateAttribute($value): void
    {
        $this->attributes['date'] = \Carbon\Carbon::parse($value)->toDateString();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'empid', 'empid');
    }

    public function getEarlyMinutesAttribute(): ?int
    {
        $end = config('attendance.shift_end');
        if (! $end || $this->status !== 'Present' || ! $this->check_out) {
            return null;
        }

        return max(0, (int) $this->check_out->diffInMinutes($this->date->copy()->setTimeFromTimeString($end), false));
    }

    public function getLateMinutesAttribute(): ?int
    {
        $start = config('attendance.shift_start');
        if (! $start || $this->status !== 'Present' || ! $this->check_in) {
            return null;
        }

        return max(0, (int) $this->date->copy()->setTimeFromTimeString($start)->diffInMinutes($this->check_in, false));
    }
}
