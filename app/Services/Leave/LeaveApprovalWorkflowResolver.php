<?php

namespace App\Services\Leave;

use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveApplicationApproval;
use App\Models\LeaveApprovalWorkflow;
use App\Models\LeaveApprovalWorkflowStep;
use App\Models\LeavePolicyRule;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Turns a workflow configuration into concrete approval instances at submission.
 *
 * The created leave_application_approvals rows are a historical snapshot:
 * they are never recomputed when the workflow changes later.
 *
 * Supported step types (see LeaveApprovalWorkflowStep::SUPPORTED_TYPES):
 *  - immediate_supervisor: the applicant's employees.supervisor_id
 *  - higher_supervisor:    the immediate supervisor's own supervisor_id
 *  - specific_employee:    the step's approver_employee_id
 * `role` and `designation` can't be resolved to one person reliably
 * (see LeaveApprovalWorkflowStep::UNSUPPORTED_TYPES) and are refused.
 */
class LeaveApprovalWorkflowResolver
{
    /**
     * Permissions an approver's login must hold before a step is assigned to them.
     */
    public const REQUIRED_APPROVER_PERMISSIONS = ['leave.approval.view', 'leave.approval.approve'];

    /**
     * Employee statuses that can no longer act on anything.
     */
    private const INACTIVE_EMPLOYEE_STATUSES = ['archived', 'terminated'];

    /**
     * The workflow tied to the rule's policy wins; otherwise the active
     * company-wide workflow (no policy) applies.
     */
    public function resolveWorkflow(?LeavePolicyRule $rule): ?LeaveApprovalWorkflow
    {
        if ($rule) {
            $workflow = LeaveApprovalWorkflow::where('is_active', true)
                ->where('leave_policy_id', $rule->leave_policy_id)
                ->orderBy('id')
                ->first();

            if ($workflow) {
                return $workflow;
            }
        }

        return LeaveApprovalWorkflow::where('is_active', true)
            ->whereNull('leave_policy_id')
            ->orderBy('id')
            ->first();
    }

    /**
     * Create the approval instances for a newly submitted application.
     *
     * @throws ValidationException when no workflow applies, or a required step
     *                             can't be assigned to someone able to act on it
     */
    public function createApprovals(LeaveApplication $application, ?LeavePolicyRule $rule): void
    {
        $workflow = $this->resolveWorkflow($rule);

        if (! $workflow) {
            throw ValidationException::withMessages([
                'leave_type_id' => ['No active approval workflow applies to this leave. Please contact HR.'],
            ]);
        }

        $applicant = $application->employee()->firstOrFail();
        $created = 0;

        foreach ($workflow->steps as $step) {
            [$approver, $problem] = $this->resolveApprover($step, $applicant);

            if ($approver && ($problem = $this->approverProblem($approver, $applicant)) === null) {
                $application->approvals()->create([
                    'leave_approval_workflow_id' => $workflow->id,
                    'leave_approval_workflow_step_id' => $step->id,
                    'approver_id' => $approver->id,
                    'approver_type' => $step->approver_type,
                    'approver_name' => trim($approver->emp_first_name.' '.$approver->emp_last_name),
                    'approval_order' => $step->step_order,
                    'is_required' => $step->is_required,
                    'status' => LeaveApplicationApproval::STATUS_PENDING,
                ]);

                $created++;

                continue;
            }

            if ($step->is_required) {
                throw ValidationException::withMessages([
                    'approval' => ["Approval step {$step->step_order} can't be assigned: {$problem} Please contact HR."],
                ]);
            }
        }

        if ($created === 0) {
            throw ValidationException::withMessages([
                'approval' => ['No approver could be assigned to this leave application. Please contact HR.'],
            ]);
        }
    }

    /**
     * @return array{0: ?Employee, 1: ?string} the approver, or the reason there isn't one
     */
    public function resolveApprover(LeaveApprovalWorkflowStep $step, Employee $applicant): array
    {
        switch ($step->approver_type) {
            case 'immediate_supervisor':
                $supervisor = $applicant->supervisor_id ? Employee::find($applicant->supervisor_id) : null;

                return [$supervisor, $supervisor ? null : 'the applicant has no supervisor assigned.'];

            case 'higher_supervisor':
                $supervisor = $applicant->supervisor_id ? Employee::find($applicant->supervisor_id) : null;
                $higher = $supervisor?->supervisor_id ? Employee::find($supervisor->supervisor_id) : null;

                return [$higher, $higher ? null : "the applicant's supervisor has no supervisor assigned."];

            case 'specific_employee':
                $employee = $step->approver_employee_id ? Employee::find($step->approver_employee_id) : null;

                return [$employee, $employee ? null : 'the configured approver no longer exists.'];

            default:
                return [null, "approver type \"{$step->approver_type}\" is not supported."];
        }
    }

    /**
     * Null when the employee can act on an approval step, otherwise why not.
     */
    public function approverProblem(Employee $approver, ?Employee $applicant = null): ?string
    {
        if ($applicant && $approver->id === $applicant->id) {
            return 'the applicant cannot approve their own leave.';
        }

        if (in_array($approver->status, self::INACTIVE_EMPLOYEE_STATUSES, true)) {
            return 'the approver is no longer an active employee.';
        }

        $canAct = User::where('employee_id', $approver->id)
            ->where('status', 'active')
            ->get()
            ->contains(fn (User $user) => collect(self::REQUIRED_APPROVER_PERMISSIONS)->every(fn ($p) => $user->can($p)));

        return $canAct ? null : 'the approver has no active account with leave approval permission.';
    }
}
