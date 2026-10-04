<?php

namespace App\Policies;

use App\Models\OnboardingTemplate;
use App\Models\User;

class OnboardingTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('onboarding.template.view');
    }

    public function create(User $user): bool
    {
        return $user->can('onboarding.template.create');
    }

    public function update(User $user, OnboardingTemplate $template): bool
    {
        return $user->can('onboarding.template.update');
    }

    public function delete(User $user, OnboardingTemplate $template): bool
    {
        return false; // deactivate instead; existing onboardings may reference it
    }
}
