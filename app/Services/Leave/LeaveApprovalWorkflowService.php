<?php

namespace App\Services\Leave;

use App\Models\Employee;
use App\Models\LeaveApprovalWorkflow;
use App\Models\LeaveApprovalWorkflowStep;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Workflow configuration. Steps are managed as part of their workflow.
 *
 * Editing a workflow only affects applications submitted afterwards: approval
 * instances already created keep their own snapshot and are never touched here.
 */
class LeaveApprovalWorkflowService
{
    /**
     * Step orders are shifted by this much while re-sequencing, to stay clear
     * of the (workflow, step_order) unique index. MAX_STEPS + offset < 255.
     */
    private const REORDER_OFFSET = 100;

    public const MAX_STEPS = 10;

    public function __construct(
        protected LeaveApprovalWorkflowResolver $resolver
    ) {}

    public function getPaginatedWorkflows(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return LeaveApprovalWorkflow::query()
            ->with([
                'leavePolicy:id,name',
                'steps.approverEmployee:id,emp_first_name,emp_last_name,employee_number',
            ])
            ->when(! empty($filters['search']), fn ($q) => $q->where('name', 'like', "%{$filters['search']}%"))
            ->when(
                isset($filters['is_active']) && $filters['is_active'] !== null && $filters['is_active'] !== '',
                fn ($q) => $q->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN))
            )
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function createWorkflow(array $data): LeaveApprovalWorkflow
    {
        return DB::transaction(function () use ($data) {
            $this->ensureSingleActive($data);
            $this->ensureApproversCanAct($data['steps']);

            $workflow = LeaveApprovalWorkflow::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'leave_policy_id' => $data['leave_policy_id'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->syncSteps($workflow, $data['steps']);

            return $workflow->load('steps');
        });
    }

    public function updateWorkflow(LeaveApprovalWorkflow $workflow, array $data): LeaveApprovalWorkflow
    {
        return DB::transaction(function () use ($workflow, $data) {
            $workflow = LeaveApprovalWorkflow::whereKey($workflow->id)->lockForUpdate()->firstOrFail();

            $this->ensureSingleActive($data, $workflow->id);
            $this->ensureApproversCanAct($data['steps']);

            $workflow->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'leave_policy_id' => $data['leave_policy_id'] ?? null,
                'is_active' => $data['is_active'] ?? $workflow->is_active,
            ]);

            $this->syncSteps($workflow, $data['steps']);

            return $workflow->load('steps');
        });
    }

    /**
     * Archive = deactivate; existing applications keep their approvers.
     */
    public function archiveWorkflow(LeaveApprovalWorkflow $workflow): bool
    {
        return $workflow->update(['is_active' => false]);
    }

    /**
     * Steps are saved in the order given (step_order = position + 1).
     * Rows with an id are updated, new rows created, missing rows deleted.
     */
    protected function syncSteps(LeaveApprovalWorkflow $workflow, array $steps): void
    {
        $keepIds = collect($steps)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

        LeaveApprovalWorkflowStep::where('leave_approval_workflow_id', $workflow->id)
            ->whereNotIn('id', $keepIds)
            ->delete();

        LeaveApprovalWorkflowStep::where('leave_approval_workflow_id', $workflow->id)
            ->update(['step_order' => DB::raw('step_order + '.self::REORDER_OFFSET)]);

        foreach (array_values($steps) as $index => $step) {
            $attributes = [
                'step_order' => $index + 1,
                'approver_type' => $step['approver_type'],
                'approver_employee_id' => $step['approver_type'] === 'specific_employee'
                    ? $step['approver_employee_id']
                    : null,
                'approver_role' => null,
                'is_required' => $step['is_required'] ?? true,
            ];

            if (! empty($step['id'])) {
                LeaveApprovalWorkflowStep::where('leave_approval_workflow_id', $workflow->id)
                    ->whereKey($step['id'])
                    ->firstOrFail()
                    ->update($attributes);
            } else {
                $workflow->steps()->create($attributes);
            }
        }
    }

    /**
     * Only one active workflow may apply per policy (and one company-wide default),
     * so resolution at submission is never ambiguous.
     */
    protected function ensureSingleActive(array $data, ?int $ignoreId = null): void
    {
        if (! ($data['is_active'] ?? true)) {
            return;
        }

        $policyId = $data['leave_policy_id'] ?? null;

        $conflict = LeaveApprovalWorkflow::where('is_active', true)
            ->when($policyId, fn ($q) => $q->where('leave_policy_id', $policyId), fn ($q) => $q->whereNull('leave_policy_id'))
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'leave_policy_id' => [$policyId
                    ? 'Another active workflow already applies to this policy. Archive it first.'
                    : 'Another active company-wide workflow already exists. Archive it first.'],
            ]);
        }
    }

    /**
     * A specific-employee approver must be able to act, or every application would fail at submission.
     */
    protected function ensureApproversCanAct(array $steps): void
    {
        foreach (array_values($steps) as $index => $step) {
            if ($step['approver_type'] !== 'specific_employee') {
                continue;
            }

            $employee = Employee::find($step['approver_employee_id']);
            $problem = $employee ? $this->resolver->approverProblem($employee) : 'the employee does not exist.';

            if ($problem !== null) {
                throw ValidationException::withMessages([
                    "steps.{$index}.approver_employee_id" => ['This approver cannot act on leave approvals: '.$problem],
                ]);
            }
        }
    }
}
