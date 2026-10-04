<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;
use App\Models\Vacancy;

/**
 * Selection decisions (application_selections). Deciding needs
 * recruitment.selection.create; the vacancy's hiring manager may view the
 * decisions for their own vacancy but not make them. Eligibility (Evaluation
 * stage, complete scorecards, openings) is re-checked in SelectionService.
 */
class SelectionPolicy
{
    /** See an application's selection decisions and evaluation summary. */
    public function viewAny(User $user, Application $application): bool
    {
        return $user->can('recruitment.selection.view')
            || ($user->employee_id !== null && Vacancy::whereKey($application->vacancy_id)->where('hiring_manager_id', $user->employee_id)->exists());
    }

    public function create(User $user, Application $application): bool
    {
        return $application->status === Application::STATUS_SHORTLISTED && $user->can('recruitment.selection.create');
    }
}
