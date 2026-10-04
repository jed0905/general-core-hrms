<?php

namespace App\Services\Recruitment;

use App\Models\ApprovalWorkflow;
use App\Models\Employee;
use App\Models\JobRequisition;
use App\Models\JobRequisitionApproval;
use App\Models\User;
use App\Services\ApprovalChainResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Approval instances for job requisitions.
 *
 * At submission the configured chain is resolved into concrete approvers and
 * copied into job_requisition_approvals (approver, order, type, workflow
 * name). Approvals run strictly in approval_order: only the approver on the
 * lowest pending step may act. Every decision locks the requisition and
 * re-checks state and the actor inside the transaction.
 */
class RequisitionApprovalService
{
    public const APPROVE_PERMISSION = 'recruitment.requisition.approve';

    public function __construct(protected ApprovalChainResolver $resolver) {}

    /**
     * Must be called inside the submit transaction, with the requisition locked.
     */
    public function createApprovals(JobRequisition $requisition): void
    {
        $workflow = $this->resolver->activeWorkflow(ApprovalWorkflow::MODULE_JOB_REQUISITION);

        if (! $workflow) {
            throw ValidationException::withMessages(['approval' => ['No active requisition approval workflow is configured. Please contact HR.']]);
        }

        $requester = Employee::findOrFail($requisition->requested_by_employee_id);
        $excluded = array_values(array_filter([
            $requester->id,
            User::whereKey($requisition->created_by)->value('employee_id'),
        ]));

        foreach ($this->resolver->resolve($workflow, $requester, $excluded, self::APPROVE_PERMISSION) as $order => $link) {
            $requisition->approvals()->create([
                'approval_workflow_id' => $workflow->id,
                'approval_workflow_step_id' => $link['step']->id,
                'workflow_name' => $workflow->name,
                'approval_order' => $order + 1,
                'approver_type' => $link['step']->approver_type,
                'is_required' => $link['step']->is_required,
                'approver_id' => $link['approver']->id,
                'approver_name' => trim($link['approver']->emp_first_name.' '.$link['approver']->emp_last_name),
                'status' => JobRequisitionApproval::STATUS_PENDING,
            ]);
        }
    }

    public function approve(JobRequisition $requisition, User $actor, ?string $remarks = null): JobRequisition
    {
        return DB::transaction(function () use ($requisition, $actor, $remarks) {
            [$requisition, $step] = $this->lockForDecision($requisition, $actor);

            $step->update([
                'status' => JobRequisitionApproval::STATUS_APPROVED,
                'acted_at' => now(),
                'acted_by' => $actor->id,
                'remarks' => $remarks,
            ]);

            $stillPending = $requisition->approvals()->where('status', JobRequisitionApproval::STATUS_PENDING)->exists();

            if (! $stillPending) {
                $requisition->update(['status' => JobRequisition::STATUS_APPROVED, 'approved_at' => now()]);
            }

            return $requisition->fresh();
        });
    }

    public function reject(JobRequisition $requisition, User $actor, string $remarks): JobRequisition
    {
        return DB::transaction(function () use ($requisition, $actor, $remarks) {
            [$requisition, $step] = $this->lockForDecision($requisition, $actor);

            $step->update([
                'status' => JobRequisitionApproval::STATUS_REJECTED,
                'acted_at' => now(),
                'acted_by' => $actor->id,
                'remarks' => $remarks,
            ]);

            $requisition->approvals()
                ->where('status', JobRequisitionApproval::STATUS_PENDING)
                ->update(['status' => JobRequisitionApproval::STATUS_SKIPPED]);

            $requisition->update(['status' => JobRequisition::STATUS_REJECTED, 'rejected_at' => now()]);

            return $requisition->fresh();
        });
    }

    /**
     * Pending requisitions whose current step belongs to this employee.
     */
    public function awaitingDecisionBy(Employee $approver)
    {
        return JobRequisition::query()
            ->where('status', JobRequisition::STATUS_PENDING)
            ->whereHas('approvals', fn ($q) => $q
                ->where('approver_id', $approver->id)
                ->where('status', JobRequisitionApproval::STATUS_PENDING)
                ->whereNotExists(fn ($earlier) => $earlier
                    ->selectRaw('1')
                    ->from('job_requisition_approvals as earlier')
                    ->whereColumn('earlier.job_requisition_id', 'job_requisition_approvals.job_requisition_id')
                    ->whereColumn('earlier.approval_order', '<', 'job_requisition_approvals.approval_order')
                    ->where('earlier.status', JobRequisitionApproval::STATUS_PENDING)));
    }

    /**
     * Lock the requisition and its current step, and re-check that this actor may decide now.
     *
     * @return array{0: JobRequisition, 1: JobRequisitionApproval}
     */
    protected function lockForDecision(JobRequisition $requisition, User $actor): array
    {
        $requisition = JobRequisition::whereKey($requisition->id)->lockForUpdate()->firstOrFail();

        if (! $requisition->isPending()) {
            throw ValidationException::withMessages(['status' => ['This requisition is no longer awaiting approval.']]);
        }

        $step = $requisition->approvals()
            ->where('status', JobRequisitionApproval::STATUS_PENDING)
            ->orderBy('approval_order')
            ->lockForUpdate()
            ->first();

        if (! $step || $actor->employee_id === null || $step->approver_id !== $actor->employee_id || ! $actor->can(self::APPROVE_PERMISSION)) {
            throw ValidationException::withMessages(['approval' => ['You are not the approver for the current step of this requisition.']]);
        }

        if (in_array($actor->employee_id, [$requisition->requested_by_employee_id, User::whereKey($requisition->created_by)->value('employee_id')], true)) {
            throw ValidationException::withMessages(['approval' => ['You cannot approve your own requisition.']]);
        }

        return [$requisition, $step];
    }
}
