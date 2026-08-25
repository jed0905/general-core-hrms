<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeeklyShiftTemplate extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description'];

    /**
     * Relationship: has many days (Mon–Sun).
     */
    public function days()
    {
        return $this->hasMany(WeeklyShiftDay::class);
    }

    public function weeklyShiftDays()
    {
        return $this->hasMany(WeeklyShiftDay::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'work_shift', 'id');
    }

    public function getWeeklyShiftDaysAttribute()
    {
        return $this->weeklyShiftDays()
            ->with('dailyShiftSchedule')
            ->get()
            ->pluck('dailyShiftSchedule.day_of_week');
    }
}
