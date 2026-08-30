<?php

namespace App\Providers;

use App\Models\CivilServiceEligibility;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Elementary;
use App\Models\Employee;
use App\Models\EmployeeAdditionalInformation;
use App\Models\EmployeeLeaveCreditsHistory;
use App\Models\FamilyBackground;
use App\Models\GraduateStudy;
use App\Models\JobStatus;
use App\Models\LearningDevelopment;
use App\Models\LeaveApplication;
use App\Models\OperatingUnit;
use App\Models\OtherInfoNonAcademicDistinction;
use App\Models\OtherInfoOrganization;
use App\Models\OtherInfoSpecialSkills;
use App\Models\PersonalInformation;
use App\Models\Position;
use App\Models\SalaryGrade;
use App\Models\SalaryStep;
use App\Models\Secondary;
use App\Models\User;
use App\Models\Vocational;
use App\Models\VoluntaryWork;
use App\Models\WorkExperience;
use App\Observers\DepartmentObserver;
use App\Observers\EmployeeObserver;
use App\Observers\FamilyBackgroundObserver;
use App\Observers\GraduateStudyObserver;
use App\Observers\JobStatusObserver;
use App\Observers\LearningDevelopmentObserver;
use App\Observers\LeaveApplicationObserver;
use App\Observers\OperatingUnitObserver;
use App\Observers\OtherInfoNonAcademicDistinctionObserver;
use App\Observers\OtherInfoOrganizationObserver;
use App\Observers\OtherInfoSpecialSkillsObserver;
use App\Observers\PersonalInformationObserver;
use App\Observers\PositionObserver;
use App\Observers\SalaryGradeObserver;
use App\Observers\SalaryStepObserver;
use App\Observers\SecondaryObserver;
use App\Observers\UserObserver;
use App\Observers\VocationalObserver;
use App\Observers\VoluntaryWorkObserver;
use App\Observers\WorkExperienceObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        \Illuminate\Auth\Events\Login::class => [
            \App\Listeners\LogSuccessfulLogin::class,
        ],
        \Illuminate\Auth\Events\Logout::class => [
            \App\Listeners\LogSuccessfulLogout::class,
        ],
        \Illuminate\Auth\Events\Failed::class => [
            \App\Listeners\LogFailedLogin::class,
        ],
    ];

    /**
     * The model observers for your application.
     *
     * @var array<class-string, class-string>
     */
    protected $observers = [
        // For Audit Trails
        Department::class => DepartmentObserver::class,
        Employee::class => EmployeeObserver::class,
        LeaveApplication::class => LeaveApplicationObserver::class,
        User::class => UserObserver::class,
        WorkExperience::class => WorkExperienceObserver::class,
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void {}

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
