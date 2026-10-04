<?php

namespace App\Policies;

use App\Models\Onboarding;
use App\Models\OnboardingTask;
use App\Models\OnboardingTemplateTask;
use App\Models\User;

/**
 * Who may act on an onboarding task, from the task's stored assignment
 * snapshot (never the employee's current supervisor):
 * - HR with onboarding.task.update: any task;
 * - the employee (onboarding.update_own): their own employee-assigned tasks;
 * - the snapshotted supervisor / designated employee (onboarding.update_own):
 *   the tasks assigned to them, and nothing else of the onboarding;
 * - "HR" tasks: HR only.
 */
class OnboardingTaskPolicy
{
    public function view(User $user, OnboardingTask $task): bool
    {
        return $user->can('onboarding.view') || $this->isOwnVisible($user, $task) || $this->isAssignee($user, $task);
    }

    /** Start or complete. */
    public function act(User $user, OnboardingTask $task): bool
    {
        if (! $task->isOpen() || ! $this->onboardingActive($task)) {
            return false;
        }
        if ($user->can('onboarding.task.update')) {
            return true;
        }
        if (! $user->can('onboarding.update_own')) {
            return false;
        }

        return match ($task->assignee_type) {
            OnboardingTemplateTask::ASSIGNEE_EMPLOYEE => $this->isOwnVisible($user, $task),
            OnboardingTemplateTask::ASSIGNEE_SUPERVISOR, OnboardingTemplateTask::ASSIGNEE_SPECIFIC => $this->isAssignee($user, $task),
            default => false,
        };
    }

    public function verify(User $user, OnboardingTask $task): bool
    {
        return $task->status === OnboardingTask::STATUS_COMPLETED && $task->requires_verification && $task->verified_at === null
            && $this->onboardingActive($task) && $user->can('onboarding.task.verify');
    }

    /** Optional tasks only. */
    public function skip(User $user, OnboardingTask $task): bool
    {
        return ! $task->is_required && $task->isOpen() && $this->onboardingActive($task) && $user->can('onboarding.update');
    }

    protected function isOwnVisible(User $user, OnboardingTask $task): bool
    {
        return $user->employee_id !== null && $task->employee_visible && $user->can('onboarding.view_own')
            && Onboarding::whereKey($task->onboarding_id)->where('employee_id', $user->employee_id)->exists();
    }

    protected function isAssignee(User $user, OnboardingTask $task): bool
    {
        return $user->employee_id !== null && $task->assignee_employee_id === $user->employee_id
            && in_array($task->assignee_type, [OnboardingTemplateTask::ASSIGNEE_SUPERVISOR, OnboardingTemplateTask::ASSIGNEE_SPECIFIC], true)
            && $user->can('onboarding.view_own');
    }

    protected function onboardingActive(OnboardingTask $task): bool
    {
        return Onboarding::whereKey($task->onboarding_id)->whereIn('status', Onboarding::ACTIVE)->exists();
    }
}
