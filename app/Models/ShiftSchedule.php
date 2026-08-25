<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'shift_id',
        'schedule_start_date',
        'schedule_end_date'
    ];

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }
}
