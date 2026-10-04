<?php

namespace App\Policies;

use App\Models\Onboarding;
use App\Models\User;

/**
 * Onboarding cases. Management is HR (onboarding.*). Employees reach only
 * their own onboarding through self-service (onboarding.view_own), and
 * supervisors only the tasks assigned to them (OnboardingTaskPolicy). Hiring
 * managers and payroll get nothing from their recruitment/payroll roles.
 */
class OnboardingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('onboarding.view');
    }

    public function view(User $user, Onboarding $onboarding): bool
    {
        return $user->can('onboarding.view');
    }

    public function create(User $user): bool
    {
        return $user->can('onboarding.create');
    }

    /** Add tasks and notes to an active case. */
    public function update(User $user, Onboarding $onboarding): bool
    {
        return $onboarding->isActive() && $user->can('onboarding.update');
    }

    public function complete(User $user, Onboarding $onboarding): bool
    {
        return $onboarding->isActive() && $user->can('onboarding.complete');
    }

    public function cancel(User $user, Onboarding $onboarding): bool
    {
        return $onboarding->isActive() && $user->can('onboarding.cancel');
    }

    /** Self-service "My Onboarding": always the user's own employee record. */
    public function viewMine(User $user): bool
    {
        return $user->can('onboarding.view_own');
    }

    public function delete(User $user, Onboarding $onboarding): bool
    {
        return false;
    }
}
