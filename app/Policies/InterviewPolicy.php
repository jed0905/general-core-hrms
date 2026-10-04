<?php

namespace App\Policies;

use App\Models\ApplicationInterview;
use App\Models\User;
use App\Models\Vacancy;

/**
 * Interviews. recruitment.interview.* are organization-wide (HR). The vacancy's
 * hiring manager may view its interviews; an assigned panelist may view that
 * interview (and nothing else about the application) and write their own
 * scorecard. Being a panelist is an assignment, not a role. State rules
 * (scheduled/completed/cancelled) are re-checked in InterviewService.
 */
class InterviewPolicy
{
    /** My Interviews: any user linked to an employee (the list only shows their own panels). */
    public function viewMine(User $user): bool
    {
        return $user->employee_id !== null;
    }

    public function view(User $user, ApplicationInterview $interview): bool
    {
        return $user->can('recruitment.interview.view')
            || $this->isHiringManager($user, $interview)
            || $interview->hasPanelist($user->employee_id);
    }

    /** Edit details, reschedule, cancel or complete (only while scheduled). */
    public function update(User $user, ApplicationInterview $interview): bool
    {
        return $interview->isScheduled() && $user->can('recruitment.interview.update');
    }

    /** Write one's own scorecard: assigned panelist of a completed interview. */
    public function evaluate(User $user, ApplicationInterview $interview): bool
    {
        return $interview->status === ApplicationInterview::STATUS_COMPLETED && $interview->hasPanelist($user->employee_id);
    }

    /** See every submitted scorecard of this interview. */
    public function viewEvaluations(User $user, ApplicationInterview $interview): bool
    {
        return $user->can('recruitment.evaluation.view') || $this->isHiringManager($user, $interview);
    }

    protected function isHiringManager(User $user, ApplicationInterview $interview): bool
    {
        return $user->employee_id !== null && Vacancy::query()
            ->whereHas('applications', fn ($q) => $q->whereKey($interview->application_id))
            ->where('hiring_manager_id', $user->employee_id)
            ->exists();
    }
}
