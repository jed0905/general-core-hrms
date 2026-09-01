<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkScheduleDay extends Model
{
    protected $fillable = [
        'work_schedule_id',
        'day_of_week',
        'shift_id',
        'is_working_day',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_working_day' => 'boolean',
    ];

    public function schedule()
    {
        return $this->belongsTo(WorkSchedule::class, 'work_schedule_id');
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }
}
