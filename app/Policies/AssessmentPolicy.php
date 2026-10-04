<?php

namespace App\Policies;

use App\Models\ApplicationAssessment;
use App\Models\User;
use App\Models\Vacancy;

/**
 * Assessments: HR manages them (recruitment.assessment.*); the vacancy's hiring
 * manager may view them. State rules are re-checked in AssessmentService.
 */
class AssessmentPolicy
{
    public function view(User $user, ApplicationAssessment $assessment): bool
    {
        return $user->can('recruitment.assessment.view')
            || ($user->employee_id !== null && Vacancy::query()
                ->whereHas('applications', fn ($q) => $q->whereKey($assessment->application_id))
                ->where('hiring_manager_id', $user->employee_id)
                ->exists());
    }

    /** Edit, start, complete or cancel (only while scheduled or in progress). */
    public function update(User $user, ApplicationAssessment $assessment): bool
    {
        return $assessment->isOpen() && $user->can('recruitment.assessment.update');
    }
}
