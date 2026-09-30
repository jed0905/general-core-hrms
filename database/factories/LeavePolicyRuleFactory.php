<?php

namespace Database\Factories;

use App\Models\LeavePolicy;
use App\Models\LeavePolicyRule;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeavePolicyRule>
 */
class LeavePolicyRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'leave_policy_id' => LeavePolicy::factory(),
            'leave_type_id' => LeaveType::factory(),
            'accrual_method' => 'none',
            'accrual_rate' => null,
            'grant_frequency' => 'none',
            'maximum_balance' => null,
            'carry_forward_limit' => null,
            'minimum_service_months' => 0,
            'waiting_period' => 0,
            'allow_negative' => false,
            'requires_approval' => true,
            'requires_attachment' => false,
            'allows_half_day' => true,
            'allows_hourly' => false,
            'expires' => false,
            'is_active' => true,
        ];
    }
}
