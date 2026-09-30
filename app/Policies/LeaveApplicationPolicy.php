<?php

namespace App\Policies;

use App\Models\LeaveApplication;
use App\Models\LeaveApplicationApproval;
use App\Models\User;

/**
 * Record-level authorization for leave applications.
 *
 * Capability permissions (spatie) say what kind of action a user may take;
 * this policy adds whose application it is, who the assigned approvers are,
 * and whether the application's state still allows the action.
 * Role names are never checked here.
 */
class LeaveApplicationPolicy
{
    /**
     * Owner, an approver assigned to this application, or HR with leave.view.
     */
    public function view(User $user, LeaveApplication $application): bool
    {
        if ($user->can('leave.view')) {
            return true;
        }

        if ($application->isOwnedBy($user->employee_id) && $user->can('leave.view_own')) {
            return true;
        }

        return $user->can('leave.approval.view')
            && $application->hasApprover($user->employee_id);
    }

    /**
     * Attachments, comments and status history follow the same rule as view.
     */
    public function viewAttachments(User $user, LeaveApplication $application): bool
    {
        return $this->view($user, $application);
    }

    /**
     * Returned applications can always be corrected. Pending ones only until
     * the first approver has signed off, so nobody approves content that
     * changes afterwards.
     */
    public function update(User $user, LeaveApplication $application): bool
    {
        if (! $this->isEditable($application)) {
            return false;
        }

        if ($application->isOwnedBy($user->employee_id) && $user->can('leave.update_own')) {
            return true;
        }

        return $user->can('leave.update');
    }

    /**
     * Owners can withdraw their own pending/returned requests. Cancelling an
     * approved application reverses used balance, so it needs leave.cancel.
     */
    public function cancel(User $user, LeaveApplication $application): bool
    {
        if (in_array($application->status, LeaveApplication::TERMINAL_STATUSES, true)) {
            return false;
        }

        $withdrawable = in_array($application->status, [
            LeaveApplication::STATUS_PENDING,
            LeaveApplication::STATUS_RETURNED,
        ], true);

        if ($withdrawable && $application->isOwnedBy($user->employee_id) && $user->can('leave.cancel_own')) {
            return true;
        }

        return $user->can('leave.cancel');
    }

    /**
     * Owner and assigned approvers, while the application is still open.
     */
    public function comment(User $user, LeaveApplication $application): bool
    {
        if (in_array($application->status, LeaveApplication::FINAL_STATUSES, true)) {
            return false;
        }

        if ($application->isOwnedBy($user->employee_id) && $user->can('leave.view_own')) {
            return true;
        }

        return $user->can('leave.approval.view')
            && $application->hasApprover($user->employee_id);
    }

    public function approve(User $user, LeaveApplication $application): bool
    {
        return $this->canActOnCurrentStep($user, $application, 'leave.approval.approve');
    }

    public function reject(User $user, LeaveApplication $application): bool
    {
        return $this->canActOnCurrentStep($user, $application, 'leave.approval.reject');
    }

    public function return(User $user, LeaveApplication $application): bool
    {
        return $this->canActOnCurrentStep($user, $application, 'leave.approval.return');
    }

    /**
     * Having the capability is not enough: the user must be the approver on
     * the lowest pending step of this particular application.
     */
    private function canActOnCurrentStep(User $user, LeaveApplication $application, string $permission): bool
    {
        if (! $user->can($permission) || $user->employee_id === null) {
            return false;
        }

        if ($application->status !== LeaveApplication::STATUS_PENDING) {
            return false;
        }

        $current = $application->currentApproval();

        return $current !== null && (int) $current->approver_id === (int) $user->employee_id;
    }

    private function isEditable(LeaveApplication $application): bool
    {
        if ($application->status === LeaveApplication::STATUS_RETURNED) {
            return true;
        }

        if ($application->status !== LeaveApplication::STATUS_PENDING) {
            return false;
        }

        return ! $application->approvals()
            ->where('status', '!=', LeaveApplicationApproval::STATUS_PENDING)
            ->exists();
    }
}
