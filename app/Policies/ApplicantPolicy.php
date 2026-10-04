<?php

namespace App\Policies;

use App\Models\Applicant;
use App\Models\User;

/**
 * The applicant registry is HR data: recruitment.applicant.* only. Hiring
 * managers see applicants through the applications on their own vacancies,
 * not through the registry. Role names are never checked.
 */
class ApplicantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('recruitment.applicant.view');
    }

    public function view(User $user, Applicant $applicant): bool
    {
        return $user->can('recruitment.applicant.view');
    }

    public function create(User $user): bool
    {
        return $user->can('recruitment.applicant.create');
    }

    public function update(User $user, Applicant $applicant): bool
    {
        return $user->can('recruitment.applicant.update');
    }

    /** Upload or delete the applicant's documents. */
    public function manageDocuments(User $user, Applicant $applicant): bool
    {
        return $user->can('recruitment.applicant.update');
    }
}
