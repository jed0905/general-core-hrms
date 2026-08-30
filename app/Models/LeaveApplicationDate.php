<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $leave_application_id
 * @property string $leave_date
 * @property string $duration_type
 * @property numeric|null $hours
 * @property numeric|null $day_fraction
 * @property string|null $start_time
 * @property string|null $end_time
 * @property int $is_paid
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\LeaveApplication $leaveApplication
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApplicationDate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApplicationDate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApplicationDate query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApplicationDate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApplicationDate whereDayFraction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApplicationDate whereDurationType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApplicationDate whereEndTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApplicationDate whereHours($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApplicationDate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApplicationDate whereIsPaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApplicationDate whereLeaveApplicationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApplicationDate whereLeaveDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApplicationDate whereStartTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveApplicationDate whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LeaveApplicationDate extends Model
{
    use HasFactory;

    protected $fillable = [
        'leave_application_id',
        'date',
        'duration',
        'credits',
    ];

    public function leaveApplication()
    {
        return $this->belongsTo(LeaveApplication::class);
    }
}
