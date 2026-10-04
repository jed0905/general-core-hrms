<?php

namespace App\Providers;

use App\Models\Applicant;
use App\Models\ApplicantAccount;
use App\Models\ApplicantDocument;
use App\Models\Application;
use App\Models\ApplicationAssessment;
use App\Models\ApplicationEvaluation;
use App\Models\ApplicationInterview;
use App\Models\ApplicationSelection;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeMovement;
use App\Models\JobOffer;
use App\Models\JobRequisition;
use App\Models\LeaveApplication;
use App\Models\Onboarding;
use App\Models\OnboardingTask;
use App\Models\OnboardingTemplate;
use App\Models\User;
use App\Models\Vacancy;
use App\Policies\ApplicantDocumentPolicy;
use App\Policies\ApplicantPolicy;
use App\Policies\ApplicationPolicy;
use App\Policies\AssessmentPolicy;
use App\Policies\EmployeeDocumentPolicy;
use App\Policies\EmployeeMovementPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\EvaluationPolicy;
use App\Policies\InterviewPolicy;
use App\Policies\JobRequisitionPolicy;
use App\Policies\LeaveApplicationPolicy;
use App\Policies\OfferPolicy;
use App\Policies\OnboardingPolicy;
use App\Policies\OnboardingTaskPolicy;
use App\Policies\OnboardingTemplatePolicy;
use App\Policies\SelectionPolicy;
use App\Policies\VacancyPolicy;
use App\Services\Recruitment\RecruitmentAccess;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        LeaveApplication::class => LeaveApplicationPolicy::class,
        Employee::class => EmployeePolicy::class,
        EmployeeDocument::class => EmployeeDocumentPolicy::class,
        JobRequisition::class => JobRequisitionPolicy::class,
        Vacancy::class => VacancyPolicy::class,
        Applicant::class => ApplicantPolicy::class,
        ApplicantDocument::class => ApplicantDocumentPolicy::class,
        Application::class => ApplicationPolicy::class,
        ApplicationInterview::class => InterviewPolicy::class,
        ApplicationAssessment::class => AssessmentPolicy::class,
        ApplicationEvaluation::class => EvaluationPolicy::class,
        ApplicationSelection::class => SelectionPolicy::class,
        JobOffer::class => OfferPolicy::class,
        Onboarding::class => OnboardingPolicy::class,
        OnboardingTask::class => OnboardingTaskPolicy::class,
        OnboardingTemplate::class => OnboardingTemplatePolicy::class,
        EmployeeMovement::class => EmployeeMovementPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
        $this->registerApplicantGuard();

        // Recruitment dashboard: anyone who can reach requisitions or vacancies (by permission or assignment).
        Gate::define('accessRecruitment', fn (User $user) => app(RecruitmentAccess::class)->for($user)['dashboard']);
        // The method usePersonalAccessTokens() is not defined on the Sanctum facade.
        // Ensure that the Sanctum package is correctly installed and configured.
        // If the method is intended to be used, check for any updates or documentation.
        // Alternatively, consider using a different method or approach for token management.
    }

    /**
     * Careers portal: candidates sign in with their own guard, provider and
     * reset tokens, entirely separate from employee/HR users (guard "web").
     * Registered here so config/auth.php stays untouched.
     */
    protected function registerApplicantGuard(): void
    {
        config([
            'auth.guards.applicant' => ['driver' => 'session', 'provider' => 'applicant_accounts'],
            'auth.providers.applicant_accounts' => ['driver' => 'eloquent', 'model' => ApplicantAccount::class],
            'auth.passwords.applicant_accounts' => [
                'provider' => 'applicant_accounts',
                'table' => 'applicant_password_reset_tokens',
                'expire' => 60,
                'throttle' => 60,
            ],
        ]);
    }
}
