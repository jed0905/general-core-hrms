<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;
use App\Models\Vacancy;

/**
 * recruitment.application.* (and screening/shortlist) permissions are
 * organization-wide. A vacancy's assigned hiring manager may view the
 * applications to that vacancy and add notes, but not move, screen or reject
 * them. Pipeline actions are re-checked inside ApplicationPipelineService.
 * Interview panelists get no access to the application itself; see InterviewPolicy.
 * Role names are never checked.
 */
class ApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('recruitment.application.view')
            || ($user->employee_id !== null && Vacancy::where('hiring_manager_id', $user->employee_id)->exists());
    }

    public function view(User $user, Application $application): bool
    {
        return $user->can('recruitment.application.view') || $this->isHiringManager($user, $application);
    }

    public function create(User $user): bool
    {
        return $user->can('recruitment.application.create');
    }

    /** Move to the next stage (e.g. Applied → Screening, Shortlisted → Interview). */
    public function advance(User $user, Application $application): bool
    {
        return in_array($application->status, [Application::STATUS_ACTIVE, Application::STATUS_SHORTLISTED], true)
            && $user->can('recruitment.application.move_stage');
    }

    public function shortlist(User $user, Application $application): bool
    {
        return $application->status === Application::STATUS_ACTIVE && $user->can('recruitment.shortlist.create');
    }

    public function reject(User $user, Application $application): bool
    {
        return ! $application->isTerminal() && $user->can('recruitment.application.reject');
    }

    public function withdraw(User $user, Application $application): bool
    {
        return ! $application->isTerminal() && $user->can('recruitment.application.withdraw');
    }

    /** Record or change the screening result. */
    public function screen(User $user, Application $application): bool
    {
        return $application->status === Application::STATUS_ACTIVE
            && ($user->can('recruitment.screening.create') || $user->can('recruitment.screening.update'));
    }

    public function viewScreening(User $user, Application $application): bool
    {
        return $user->can('recruitment.screening.view') || $this->isHiringManager($user, $application);
    }

    public function attachDocuments(User $user, Application $application): bool
    {
        return ! $application->isTerminal() && $user->can('recruitment.application.create');
    }

    public function addNote(User $user, Application $application): bool
    {
        return $this->view($user, $application);
    }

    public function viewInterviews(User $user, Application $application): bool
    {
        return $user->can('recruitment.interview.view') || $this->isHiringManager($user, $application);
    }

    public function scheduleInterview(User $user, Application $application): bool
    {
        return ! $application->isTerminal() && $user->can('recruitment.interview.create');
    }

    public function viewAssessments(User $user, Application $application): bool
    {
        return $user->can('recruitment.assessment.view') || $this->isHiringManager($user, $application);
    }

    public function createAssessment(User $user, Application $application): bool
    {
        return ! $application->isTerminal() && $user->can('recruitment.assessment.create');
    }

    /** Submitted scorecards of every interview of the application. */
    public function viewEvaluations(User $user, Application $application): bool
    {
        return $user->can('recruitment.evaluation.view') || $this->isHiringManager($user, $application);
    }

    /**
     * Convert the application's accepted offer into an employee. The service
     * re-checks the offer, the Core HR permissions it exercises and that the
     * application was not converted already.
     */
    public function convert(User $user, Application $application): bool
    {
        return ! $application->isTerminal()
            && $user->can('recruitment.conversion.create')
            && $user->can('employee.create')
            && $user->can('employee_movement.create');
    }

    /** The conversion section (eligibility and the recorded hand-off). */
    public function viewConversion(User $user, Application $application): bool
    {
        return $user->can('recruitment.conversion.view') || $this->isHiringManager($user, $application);
    }

    protected function isHiringManager(User $user, Application $application): bool
    {
        return $user->employee_id !== null
            && Vacancy::whereKey($application->vacancy_id)->where('hiring_manager_id', $user->employee_id)->exists();
    }
}
