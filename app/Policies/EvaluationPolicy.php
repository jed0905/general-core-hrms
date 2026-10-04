<?php

namespace App\Policies;

use App\Models\ApplicationEvaluation;
use App\Models\User;
use App\Models\Vacancy;

/**
 * Scorecards. The owner (the evaluating employee) sees and edits their own
 * draft; others see a scorecard only once it is submitted, and only with
 * recruitment.evaluation.view or as the vacancy's hiring manager. Submitted
 * scorecards can't be updated or deleted by anyone.
 */
class EvaluationPolicy
{
    public function view(User $user, ApplicationEvaluation $evaluation): bool
    {
        if ($this->owns($user, $evaluation)) {
            return true;
        }

        return $evaluation->isSubmitted() && ($user->can('recruitment.evaluation.view') || ($user->employee_id !== null && Vacancy::query()
            ->whereHas('applications', fn ($q) => $q->whereKey($evaluation->application_id))
            ->where('hiring_manager_id', $user->employee_id)
            ->exists()));
    }

    public function update(User $user, ApplicationEvaluation $evaluation): bool
    {
        return ! $evaluation->isSubmitted() && $this->owns($user, $evaluation);
    }

    public function delete(User $user, ApplicationEvaluation $evaluation): bool
    {
        return false;
    }

    protected function owns(User $user, ApplicationEvaluation $evaluation): bool
    {
        return $user->employee_id !== null && $evaluation->evaluator_employee_id === $user->employee_id;
    }
}
