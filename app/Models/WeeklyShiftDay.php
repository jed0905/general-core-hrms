<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeeklyShiftDay extends Model
{
    use HasFactory;
    protected $fillable = [
        'weekly_shift_template_id',
        'daily_shift_schedule_id',
    ];

    public function weeklyShiftTemplate()
    {
        return $this->belongsTo(WeeklyShiftTemplate::class);
    }

    public function dailyShiftSchedule()
    {
        return $this->belongsTo(DailyShiftSchedule::class);
    }
}
