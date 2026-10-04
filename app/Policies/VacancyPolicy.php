<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vacancy;

/**
 * recruitment.vacancy.* permissions are organization-wide. A hiring manager is
 * assigned per vacancy (vacancies.hiring_manager_id): they may view and edit
 * the content of their own vacancies only, and never change status.
 * Role names are never checked.
 */
class VacancyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('recruitment.vacancy.view') || $this->managesAny($user);
    }

    public function view(User $user, Vacancy $vacancy): bool
    {
        return $user->can('recruitment.vacancy.view') || $this->isHiringManager($user, $vacancy);
    }

    public function create(User $user): bool
    {
        return $user->can('recruitment.vacancy.create');
    }

    public function update(User $user, Vacancy $vacancy): bool
    {
        return ! $vacancy->isTerminal()
            && ($user->can('recruitment.vacancy.update') || $this->isHiringManager($user, $vacancy));
    }

    /** Open, put on hold, reopen. */
    public function publish(User $user, Vacancy $vacancy): bool
    {
        return $user->can('recruitment.vacancy.publish');
    }

    /** Close, mark filled, cancel. */
    public function close(User $user, Vacancy $vacancy): bool
    {
        return $user->can('recruitment.vacancy.close');
    }

    public function isHiringManager(User $user, Vacancy $vacancy): bool
    {
        return $user->employee_id !== null && $vacancy->hiring_manager_id === $user->employee_id;
    }

    protected function managesAny(User $user): bool
    {
        return $user->employee_id !== null && Vacancy::where('hiring_manager_id', $user->employee_id)->exists();
    }
}
