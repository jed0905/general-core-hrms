<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Policies\EmployeePolicy;
use App\Policies\LeaveApplicationPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
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
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
        // The method usePersonalAccessTokens() is not defined on the Sanctum facade.
        // Ensure that the Sanctum package is correctly installed and configured.
        // If the method is intended to be used, check for any updates or documentation.
        // Alternatively, consider using a different method or approach for token management.
    }
}
