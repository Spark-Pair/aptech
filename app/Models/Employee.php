<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'empid','name','email','username','password','designation','department','shift_id','joining_date','salary','is_active',
    ];
    protected $hidden = ['password'];
    protected $casts = ['joining_date'=>'date','salary'=>'decimal:2','is_active'=>'boolean'];

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class, 'empid', 'empid');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
