<?php

namespace App\Services;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Turns a configured, module-keyed approval workflow into concrete approvers
 * for one subject employee (e.g. the requester of a job requisition). Callers
 * copy the result into their own approval rows (a snapshot); this class never
 * writes anything. Semantics match Leave's workflow resolver.
 */
class ApprovalChainResolver
{
    private const INACTIVE_EMPLOYEE_STATUSES = ['archived', 'terminated'];

    /** Step names shown in HR error messages (the workflow steps have no name of their own). */
    private const STEP_LABELS = [
        ApprovalWorkflowStep::TYPE_IMMEDIATE_SUPERVISOR => 'Immediate supervisor',
        ApprovalWorkflowStep::TYPE_HIGHER_SUPERVISOR => 'Higher supervisor',
        ApprovalWorkflowStep::TYPE_SPECIFIC_EMPLOYEE => 'Designated approver',
    ];

    public function activeWorkflow(string $module): ?ApprovalWorkflow
    {
        return ApprovalWorkflow::with('steps')
            ->where('module', $module)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  array<int, int>  $excludedApproverIds  employees who may not approve (e.g. the requester)
     * @return array<int, array{step: ApprovalWorkflowStep, approver: Employee}> in step order
     *
     * @throws ValidationException when a required step can't be assigned, or nobody can approve
     */
    public function resolve(ApprovalWorkflow $workflow, Employee $subject, array $excludedApproverIds, string $approvePermission, string $errorKey = 'approval'): array
    {
        $chain = [];

        foreach ($workflow->steps as $step) {
            [$approver, $problem] = $this->resolveApprover($step, $subject);

            if ($approver && collect($chain)->contains(fn ($link) => $link['approver']->id === $approver->id)) {
                continue; // already approves an earlier step
            }

            if ($approver && ($problem = $this->approverProblem($approver, $excludedApproverIds, $approvePermission)) === null) {
                $chain[] = ['step' => $step, 'approver' => $approver];

                continue;
            }

            if ($step->is_required) {
                throw ValidationException::withMessages([$errorKey => [$this->stepMessage($step, $approver, $problem)]]);
            }
        }

        if ($chain === []) {
            throw ValidationException::withMessages([$errorKey => ['No approver could be assigned. Please contact HR.']]);
        }

        return $chain;
    }

    /**
     * HR-facing explanation of a required step that can't be assigned. It names
     * the internal approver (never ids or account details) so HR can fix it.
     */
    public function stepMessage(ApprovalWorkflowStep $step, ?Employee $approver, ?string $problem): string
    {
        $label = "Approval step {$step->step_order}".(isset(self::STEP_LABELS[$step->approver_type]) ? ' ('.self::STEP_LABELS[$step->approver_type].')' : '');

        return $approver
            ? "{$label} cannot be assigned to {$this->name($approver)}. {$problem}"
            : "{$label} cannot be assigned because no approver could be resolved from the configured approval workflow: {$problem} Please review the approval workflow configuration.";
    }

    /**
     * @return array{0: ?Employee, 1: ?string} the approver, or the reason there isn't one
     */
    public function resolveApprover(ApprovalWorkflowStep $step, Employee $subject): array
    {
        switch ($step->approver_type) {
            case ApprovalWorkflowStep::TYPE_IMMEDIATE_SUPERVISOR:
                $supervisor = $subject->supervisor_id ? Employee::find($subject->supervisor_id) : null;

                return [$supervisor, $supervisor ? null : "{$this->name($subject)} has no supervisor assigned."];

            case ApprovalWorkflowStep::TYPE_HIGHER_SUPERVISOR:
                $supervisor = $subject->supervisor_id ? Employee::find($subject->supervisor_id) : null;
                $higher = $supervisor?->supervisor_id ? Employee::find($supervisor->supervisor_id) : null;

                return [$higher, $higher ? null : ($supervisor
                    ? "{$this->name($supervisor)}, the supervisor of {$this->name($subject)}, has no supervisor assigned."
                    : "{$this->name($subject)} has no supervisor assigned.")];

            case ApprovalWorkflowStep::TYPE_SPECIFIC_EMPLOYEE:
                $employee = $step->approver_employee_id ? Employee::find($step->approver_employee_id) : null;

                return [$employee, $employee ? null : 'The designated approver no longer exists.'];

            default:
                return [null, "The approver type \"{$step->approver_type}\" is not supported."];
        }
    }

    /**
     * Null when the employee can act on an approval step, otherwise why not
     * (a sentence naming the approver, with what HR should do about it).
     */
    public function approverProblem(Employee $approver, array $excludedApproverIds, string $approvePermission): ?string
    {
        $name = $this->name($approver);

        if (in_array($approver->id, $excludedApproverIds, true)) {
            return "{$name} would be approving their own request.";
        }

        if (in_array($approver->status, self::INACTIVE_EMPLOYEE_STATUSES, true)) {
            return "{$name}'s employee record is {$approver->status}.";
        }

        $users = User::where('employee_id', $approver->id)->get();
        $active = $users->where('status', 'active');

        if ($active->contains(fn (User $user) => $user->can($approvePermission))) {
            return null;
        }

        if ($users->isEmpty()) {
            return "{$name} does not have an active user account. Please create a user account for {$name} before continuing.";
        }

        if ($active->isEmpty()) {
            return "{$name}'s user account is inactive. Please activate the account before continuing.";
        }

        return "{$name}'s account does not have the required approval permission ({$approvePermission}). Please update the account's roles or permissions before continuing.";
    }

    protected function name(Employee $employee): string
    {
        return trim($employee->emp_first_name.' '.$employee->emp_last_name);
    }
}
