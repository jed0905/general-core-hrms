<?php

namespace App\Policies\Payroll;

use App\Models\StatutoryRuleVersion;
use App\Models\User;

/**
 * Statutory rule versions (any country). Drafting and verifying are separate
 * permissions, and whoever drafted a version can't verify it.
 */
class StatutoryRuleVersionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payroll.statutory_rule.view');
    }

    public function view(User $user, StatutoryRuleVersion $rule): bool
    {
        return $user->can('payroll.statutory_rule.view');
    }

    public function create(User $user): bool
    {
        return $user->can('payroll.statutory_rule.manage');
    }

    public function update(User $user, StatutoryRuleVersion $rule): bool
    {
        return $user->can('payroll.statutory_rule.manage') && $rule->isDraft();
    }

    public function verify(User $user, StatutoryRuleVersion $rule): bool
    {
        return $user->can('payroll.statutory_rule.verify') && $rule->isDraft() && (int) $rule->created_by !== $user->id;
    }

    public function reject(User $user, StatutoryRuleVersion $rule): bool
    {
        return $user->can('payroll.statutory_rule.verify') && $rule->isDraft();
    }

    public function delete(User $user, StatutoryRuleVersion $rule): bool
    {
        return false;
    }
}
