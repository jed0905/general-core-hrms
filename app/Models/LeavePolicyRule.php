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
        'requires_approval',
        'requires_attachment',
        'allows_half_day',
        'allows_hourly',
        'expires',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'leave_policy_id' => 'integer',
            'leave_type_id' => 'integer',
            'accrual_rate' => 'decimal:4',
            'maximum_balance' => 'decimal:4',
            'carry_forward_limit' => 'decimal:4',
            'minimum_service_months' => 'integer',
            'waiting_period' => 'integer',
            'allow_negative' => 'boolean',
            'requires_approval' => 'boolean',
            'requires_attachment' => 'boolean',
            'allows_half_day' => 'boolean',
            'allows_hourly' => 'boolean',
            'expires' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function policy()
    {
        return $this->belongsTo(LeavePolicy::class, 'leave_policy_id', 'id');
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id', 'id');
    }
}
