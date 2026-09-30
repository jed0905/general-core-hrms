<?php

namespace Database\Factories;

use App\Models\LeaveApprovalWorkflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveApprovalWorkflow>
 */
class LeaveApprovalWorkflowFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true).' Workflow',
            'description' => null,
            'leave_policy_id' => null,
            'is_active' => true,
        ];
    }
}
