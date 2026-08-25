<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyShiftSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'day_of_week',
        'time_in',
        'time_out',
        'break_start',
        'break_end',
        'is_csc_compliant',
        'remarks',
    ];

    /**
     * Relationship: belongs to many weekly templates.
     */
    public function weeklyShiftDays()
    {
        return $this->hasMany(WeeklyShiftDay::class);
    }


    /**
     * Check if the schedule complies with CSC 60-minute rule (10AM–2PM).
     */
    // public function checkCSCCompliance()
    // {
    //     $breakStart = Carbon::createFromFormat('H:i:s', $this->break_start);
    //     $breakEnd = Carbon::createFromFormat('H:i:s', $this->break_end);
    //     $duration = $breakEnd->diffInMinutes($breakStart);

    //     $windowStart = Carbon::createFromTime(10, 0, 0);
    //     $windowEnd = Carbon::createFromTime(14, 0, 0);

    //     $isWithinWindow = $breakStart->between($windowStart, $windowEnd) ||
    //                       $breakEnd->between($windowStart, $windowEnd);

    //     return $duration >= 60 && $isWithinWindow;
    // }

    // /**
    //  * Automatically set CSC compliance on save.
    //  */
    // protected static function booted()
    // {
    //     static::saving(function ($schedule) {
    //         $schedule->is_csc_compliant = $schedule->checkCSCCompliance();
    //     });
    // }
}
