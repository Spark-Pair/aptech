<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;
    protected $fillable = ['empid','date','check_in','check_out','status'];
    protected $casts = ['check_in'=>'datetime','check_out'=>'datetime','date'=>'date'];

    public function setDateAttribute($value): void { $this->attributes['date'] = \Carbon\Carbon::parse($value)->toDateString(); }
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class, 'empid', 'empid'); }

    private function shiftBoundary(string $field): ?\Carbon\Carbon
    {
        $shift = $this->employee?->shift;
        $time = $shift?->{$field} ?: config('attendance.'.($field === 'end_time' ? 'shift_end' : 'shift_start'));
        if (! $time) return null;
        $boundary = $this->date->copy()->setTimeFromTimeString($time);
        if ($field === 'end_time' && $shift && $shift->end_time <= $shift->start_time) $boundary->addDay();
        return $boundary;
    }

    public function getEarlyMinutesAttribute(): ?int
    {
        $end = $this->shiftBoundary('end_time');
        if (! $end || $this->status !== 'Present' || ! $this->check_out) return null;
        // Early Min = how many minutes the employee left before their assigned shift ended.
        return max(0, (int) $this->check_out->diffInMinutes($end, false));
    }

    public function getLateMinutesAttribute(): ?int
    {
        $start = $this->shiftBoundary('start_time');
        if (! $start || $this->status !== 'Present' || ! $this->check_in) return null;
        return max(0, (int) $start->diffInMinutes($this->check_in, false));
    }
}
