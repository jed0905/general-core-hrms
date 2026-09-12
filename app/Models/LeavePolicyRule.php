<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeavePolicyRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'leave_policy_id',
        'leave_type_id',
        'accrual_method',
        'accrual_rate',
        'grant_frequency',
        'maximum_balance',
        'carry_forward_limit',
        'minimum_service_months',
        'waiting_period',
        'allow_negative',
        'required_approval',
        'requires_attachment',
        'allows_half_day',
        'allows_hourly',
        'expires',
    ];

    public function policy()
    {
        return $this->belongsTo(LeavePolicy::class, 'leave_policy_id', 'id');
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }
}
