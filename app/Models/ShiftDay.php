<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiftDay extends Model
{
    protected $fillable = [
        'shift_id',
        'day_of_week',
        'is_working_day',
        'start_time',
        'end_time',
        'break_hours',
        'required_hours',
    ];
}
