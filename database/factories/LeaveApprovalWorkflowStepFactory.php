<?php

namespace Database\Factories;

use App\Models\LeaveApprovalWorkflow;
use App\Models\LeaveApprovalWorkflowStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveApprovalWorkflowStep>
 */
class LeaveApprovalWorkflowStepFactory extends Factory
{
    public function definition(): array
    {
        return [
            'leave_approval_workflow_id' => LeaveApprovalWorkflow::factory(),
            'step_order' => 1,
            'approver_type' => 'immediate_supervisor',
            'approver_employee_id' => null,
            'approver_role' => null,
            'is_required' => true,
        ];
    }
}
