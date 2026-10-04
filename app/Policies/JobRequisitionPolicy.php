<?php

namespace App\Policies;

use App\Models\JobRequisition;
use App\Models\JobRequisitionApproval;
use App\Models\User;

/**
 * recruitment.requisition.view is organization-wide. Without it, a user only
 * reaches requisitions they requested or created, or ones they are an approver
 * on. Approving needs the permission AND being the current step's approver.
 * Role names are never checked.
 */
class JobRequisitionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('recruitment.requisition.view')
            || $user->can('recruitment.requisition.create')
            || $user->can('recruitment.requisition.approve');
    }

    public function view(User $user, JobRequisition $requisition): bool
    {
        return $user->can('recruitment.requisition.view')
            || $this->isOwn($user, $requisition)
            || $this->isApprover($user, $requisition);
    }

    public function create(User $user): bool
    {
        // Someone must be the requester: HR picks one; everyone else requests as themselves.
        return $user->can('recruitment.requisition.create')
            && ($user->can('recruitment.requisition.view') || $user->employee_id !== null);
    }

    public function update(User $user, JobRequisition $requisition): bool
    {
        return $requisition->isDraft() && $user->can('recruitment.requisition.update') && $this->manages($user, $requisition);
    }

    public function submit(User $user, JobRequisition $requisition): bool
    {
        return $requisition->isDraft() && $user->can('recruitment.requisition.submit') && $this->manages($user, $requisition);
    }

    public function cancel(User $user, JobRequisition $requisition): bool
    {
        return in_array($requisition->status, JobRequisition::CANCELLABLE, true)
            && $user->can('recruitment.requisition.cancel')
            && $this->manages($user, $requisition);
    }

    public function approve(User $user, JobRequisition $requisition): bool
    {
        return $requisition->isPending()
            && $user->can('recruitment.requisition.approve')
            && $user->employee_id !== null
            && $requisition->currentApproval()?->approver_id === $user->employee_id;
    }

    public function reject(User $user, JobRequisition $requisition): bool
    {
        return $this->approve($user, $requisition);
    }

    /**
     * Organization-wide HR, or the requester/creator themselves.
     */
    protected function manages(User $user, JobRequisition $requisition): bool
    {
        return $user->can('recruitment.requisition.view') || $this->isOwn($user, $requisition);
    }

    protected function isOwn(User $user, JobRequisition $requisition): bool
    {
        return $requisition->created_by === $user->id
            || ($user->employee_id !== null && $requisition->requested_by_employee_id === $user->employee_id);
    }

    protected function isApprover(User $user, JobRequisition $requisition): bool
    {
        return $user->employee_id !== null
            && JobRequisitionApproval::where('job_requisition_id', $requisition->id)->where('approver_id', $user->employee_id)->exists();
    }
}
