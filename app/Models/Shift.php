<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'start_time', 'end_time', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function getDurationMinutesAttribute(): int
    {
        if (! $this->start_time || ! $this->end_time) {
            return 0;
        }

        // SQLite/existing data may return H:i, H:i:s, or values with surrounding whitespace.
        // Carbon::parse safely accepts all of these instead of requiring one exact format.
        $start = Carbon::parse(trim((string) $this->start_time));
        $end = Carbon::parse(trim((string) $this->end_time));

        if ($end->lte($start)) {
            $end->addDay();
        }

        return $start->diffInMinutes($end);
    }
}
