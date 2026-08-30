<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read \App\Models\Employee|null $actedBy
 * @property-read \App\Models\LeaveApplication|null $leaveApplication
 * @property-read \App\Models\Employee|null $signatory
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveStatus newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveStatus newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeaveStatus query()
 * @mixin \Eloquent
 */
class LeaveStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'leave_application_id',
        'status',
        'remarks',
        'acted_by',
        'signatory_id',
        'acted_at',
    ];


    public function leaveApplication()
    {
        return $this->belongsTo(LeaveApplication::class);
    }

    public function actedBy()
    {
        return $this->belongsTo(Employee::class, 'acted_by');
    }

    public function signatory()
    {
        return $this->belongsTo(Employee::class, 'signatory_id');
    }
}
