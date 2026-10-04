<?php

use App\Http\Controllers\Web\People\EmployeeCompensationController;
use App\Http\Controllers\Web\People\EmployeeContactController;
use App\Http\Controllers\Web\People\EmployeeController;
use App\Http\Controllers\Web\People\EmployeeDocumentController;
use App\Http\Controllers\Web\People\EmployeeEducationController;
use App\Http\Controllers\Web\People\EmployeeEmploymentController;
use App\Http\Controllers\Web\People\EmployeeExperienceController;
use App\Http\Controllers\Web\People\EmployeeMovementController;
use App\Http\Controllers\Web\People\EmployeeMovementTypeController;
use App\Http\Controllers\Web\People\EmployeePersonalController;
use App\Http\Controllers\Web\People\EmploymentStatusController;
use App\Http\Controllers\Web\People\JobTitleController;
use App\Http\Controllers\Web\People\MyEmploymentHistoryController;
use App\Http\Controllers\Web\People\MyOnboardingController;
use App\Http\Controllers\Web\People\MyProfileController;
use App\Http\Controllers\Web\People\OnboardingController;
use App\Http\Controllers\Web\People\OnboardingTaskController;
use App\Http\Controllers\Web\People\OnboardingTemplateController;
use App\Models\Onboarding;
use App\Models\OnboardingTemplate;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('people/employees')->name('people.employee.')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Core Employee Management Routes
    |--------------------------------------------------------------------------
    */

    // Export & Import (placed above /{employee} to avoid route collision)
    Route::get('/export', [EmployeeController::class, 'export'])
        ->middleware('can:employee.export')
        ->name('export');

    Route::post('/import', [EmployeeController::class, 'import'])
        ->middleware('can:employee.import')
        ->name('import');

    // Create Employee (MUST be placed BEFORE /{employee})
    Route::middleware('can:employee.create')->group(function () {
        Route::get('/create', [EmployeeController::class, 'create'])->name('create');
        Route::post('/', [EmployeeController::class, 'store'])->name('store');
    });

    // View Employees
    Route::middleware('can:employee.view')->group(function () {
        Route::get('/', [EmployeeController::class, 'index'])->name('index');
        Route::get('/{employee}', [EmployeeController::class, 'show'])->name('show');
    });
    // Update Employee General Info
    Route::middleware('can:employee.update')->group(function () {
        Route::get('/{employee}/edit', [EmployeeController::class, 'edit'])->name('edit');
        Route::put('/{employee}', [EmployeeController::class, 'update'])->name('update');
    });

    // Archive & Restore
    Route::patch('/{employee}/archive', [EmployeeController::class, 'archive'])
        ->middleware('can:employee.archive')
        ->name('archive');

    Route::patch('/{employee}/restore', [EmployeeController::class, 'restore'])
        ->middleware('can:employee.restore')
        ->name('restore');

    // Documents (private disk; listed on the profile page, files served only through these routes)
    Route::prefix('{employee}/documents')->name('documents.')->scopeBindings()->group(function () {
        Route::post('/', [EmployeeDocumentController::class, 'store'])->middleware('can:employee.documents.create')->name('store');
        Route::put('/{document}', [EmployeeDocumentController::class, 'update'])->middleware('can:employee.documents.update')->name('update');
        Route::delete('/{document}', [EmployeeDocumentController::class, 'destroy'])->middleware('can:employee.documents.delete')->name('destroy');
        Route::get('/{document}/download', [EmployeeDocumentController::class, 'download'])->middleware('can:employee.documents.view')->name('download');
        Route::get('/{document}/view', [EmployeeDocumentController::class, 'view'])->middleware('can:employee.documents.view')->name('view');
    });

    /*
    |--------------------------------------------------------------------------
    | Sub-Resource & Profile Section Routes
    |--------------------------------------------------------------------------
    */
    // Route::prefix('{employee}')->group(function () {

    //     // Personal Information
    //     Route::prefix('personal')->name('personal.')->group(function () {
    //         Route::get('/', [EmployeePersonalController::class, 'show'])->middleware('can:employee.personal.view')->name('show');
    //         Route::put('/', [EmployeePersonalController::class, 'update'])->middleware('can:employee.personal.update')->name('update');
    //     });

    //     // Employment Details
    //     Route::prefix('employment')->name('employment.')->group(function () {
    //         Route::get('/', [EmployeeEmploymentController::class, 'show'])->middleware('can:employee.employment.view')->name('show');
    //         Route::put('/', [EmployeeEmploymentController::class, 'update'])->middleware('can:employee.employment.update')->name('update');
    //     });

    //     // Compensation
    //     Route::prefix('compensation')->name('compensation.')->group(function () {
    //         Route::get('/', [EmployeeCompensationController::class, 'show'])->middleware('can:employee.compensation.view')->name('show');
    //         Route::put('/', [EmployeeCompensationController::class, 'update'])->middleware('can:employee.compensation.update')->name('update');
    //     });

    //     // Emergency & Personal Contacts
    //     Route::prefix('contacts')->name('contacts.')->group(function () {
    //         Route::get('/', [EmployeeContactController::class, 'show'])->middleware('can:employee.contacts.view')->name('show');
    //         Route::put('/', [EmployeeContactController::class, 'update'])->middleware('can:employee.contacts.update')->name('update');
    //     });

    //     // Documents
    //     Route::prefix('documents')->name('documents.')->group(function () {
    //         Route::get('/', [EmployeeDocumentController::class, 'index'])->middleware('can:employee.documents.view')->name('index');
    //         Route::post('/', [EmployeeDocumentController::class, 'store'])->middleware('can:employee.documents.create')->name('store');
    //         Route::put('/{document}', [EmployeeDocumentController::class, 'update'])->middleware('can:employee.documents.update')->name('update');
    //         Route::delete('/{document}', [EmployeeDocumentController::class, 'destroy'])->middleware('can:employee.documents.delete')->name('destroy');
    //     });

    //     // Education Background
    //     Route::prefix('education')->name('education.')->group(function () {
    //         Route::get('/', [EmployeeEducationController::class, 'index'])->middleware('can:employee.education.view')->name('index');
    //         Route::post('/', [EmployeeEducationController::class, 'store'])->middleware('can:employee.education.create')->name('store');
    //         Route::put('/{education}', [EmployeeEducationController::class, 'update'])->middleware('can:employee.education.update')->name('update');
    //         Route::delete('/{education}', [EmployeeEducationController::class, 'destroy'])->middleware('can:employee.education.delete')->name('destroy');
    //     });

    //     // Work Experience
    //     Route::prefix('experience')->name('experience.')->group(function () {
    //         Route::get('/', [EmployeeExperienceController::class, 'index'])->middleware('can:employee.experience.view')->name('index');
    //         Route::post('/', [EmployeeExperienceController::class, 'store'])->middleware('can:employee.experience.create')->name('store');
    //         Route::put('/{experience}', [EmployeeExperienceController::class, 'update'])->middleware('can:employee.experience.update')->name('update');
    //         Route::delete('/{experience}', [EmployeeExperienceController::class, 'destroy'])->middleware('can:employee.experience.delete')->name('destroy');
    //     });
    // });
});

Route::middleware(['auth', 'verified'])
    ->prefix('people/job-titles')
    ->name('people.job-title.')
    ->group(function () {

        // View Job Titles
        Route::get('/', [JobTitleController::class, 'index'])
            ->middleware('can:job_title.view')
            ->name('index');

        // Create Job Title
        Route::post('/', [JobTitleController::class, 'store'])
            ->middleware('can:job_title.create')
            ->name('store');

        // Update Job Title
        Route::put('/{jobTitle}', [JobTitleController::class, 'update'])
            ->middleware('can:job_title.update')
            ->name('update');

        // Archive Job Title
        Route::patch('/{jobTitle}/archive', [JobTitleController::class, 'archive'])
            ->middleware('can:job_title.archive')
            ->name('archive');
    });

Route::middleware(['auth', 'verified'])
    ->prefix('people/employment-statuses')
    ->name('people.employment-status.')
    ->group(function () {

        // View Employment Statuses
        Route::get('/', [EmploymentStatusController::class, 'index'])
            ->middleware('can:employment_status.view')
            ->name('index');

        // Create Employment Status
        Route::post('/', [EmploymentStatusController::class, 'store'])
            ->middleware('can:employment_status.create')
            ->name('store');

        // Update Employment Status
        Route::put('/{employmentStatus}', [EmploymentStatusController::class, 'update'])
            ->middleware('can:employment_status.update')
            ->name('update');

        // Archive Employment Status
        Route::patch('/{employmentStatus}/archive', [EmploymentStatusController::class, 'archive'])
            ->middleware('can:employment_status.archive')
            ->name('archive');
    });

/*
|--------------------------------------------------------------------------
| Employee Self-Service: My Profile
|--------------------------------------------------------------------------
|
| Always the logged-in user's own employee record (users.employee_id).
| There is no {employee} parameter, so another employee can't be targeted.
|
*/
Route::middleware(['auth', 'verified'])
    ->prefix('people/my-profile')
    ->name('people.my-profile.')
    ->group(function () {
        Route::get('/', [MyProfileController::class, 'show'])
            ->middleware('can:employee.view_own')
            ->name('show');

        Route::put('/', [MyProfileController::class, 'update'])
            ->middleware('can:employee.update_own')
            ->name('update');
    });

/*
|--------------------------------------------------------------------------
| Employee Movements (employment history)
|--------------------------------------------------------------------------
|
| Capability via employee_movement.* permissions; record rules via
| EmployeeMovementPolicy. Static paths come before /{employeeMovement}.
|
*/
Route::middleware(['auth', 'verified'])
    ->prefix('people/employee-movements')
    ->name('people.employee-movements.')
    ->group(function () {
        Route::get('/', [EmployeeMovementController::class, 'index'])
            ->middleware('can:employee_movement.view')
            ->name('index');
        Route::get('/create', [EmployeeMovementController::class, 'create'])
            ->middleware('can:employee_movement.create')
            ->name('create');
        Route::get('/export', [EmployeeMovementController::class, 'export'])
            ->middleware('can:employee_movement.export')
            ->name('export');
        Route::post('/', [EmployeeMovementController::class, 'store'])
            ->middleware('can:employee_movement.create')
            ->name('store');
        Route::get('/{employeeMovement}', [EmployeeMovementController::class, 'show'])
            ->middleware('can:view,employeeMovement')
            ->name('show');
        Route::put('/{employeeMovement}', [EmployeeMovementController::class, 'update'])
            ->middleware('can:employee_movement.update')
            ->name('update');
        Route::post('/{employeeMovement}/cancel', [EmployeeMovementController::class, 'cancel'])
            ->middleware('can:employee_movement.cancel')
            ->name('cancel');
    });

Route::middleware(['auth', 'verified'])
    ->prefix('people/employee-movement-types')
    ->name('people.employee-movement-types.')
    ->group(function () {
        Route::get('/', [EmployeeMovementTypeController::class, 'index'])
            ->middleware('can:employee_movement_type.view')
            ->name('index');
        Route::post('/', [EmployeeMovementTypeController::class, 'store'])
            ->middleware('can:employee_movement_type.create')
            ->name('store');
        Route::put('/{employeeMovementType}', [EmployeeMovementTypeController::class, 'update'])
            ->middleware('can:employee_movement_type.update')
            ->name('update');
        Route::delete('/{employeeMovementType}', [EmployeeMovementTypeController::class, 'destroy'])
            ->middleware('can:employee_movement_type.archive')
            ->name('destroy');
    });

// Employee self-service: own effective movements only.
Route::middleware(['auth', 'verified'])
    ->get('people/my-employment-history', [MyEmploymentHistoryController::class, 'index'])
    ->middleware('can:employee_movement.view_own')
    ->name('people.my-employment-history.index');

/*
|--------------------------------------------------------------------------
| Employee onboarding (phase 6)
|--------------------------------------------------------------------------
| HR manages cases and templates (onboarding.*). Task actions are shared:
| OnboardingTaskPolicy lets HR, the employee (own tasks) or the snapshotted
| supervisor/designated employee act. Self-service always uses the
| logged-in user's own employee record.
*/
Route::middleware(['auth', 'verified'])->prefix('people/onboarding')->name('people.onboarding.')->group(function () {
    Route::get('/', [OnboardingController::class, 'index'])->middleware('can:viewAny,'.Onboarding::class)->name('index');
    Route::get('/create', [OnboardingController::class, 'create'])->middleware('can:create,'.Onboarding::class)->name('create');
    Route::post('/', [OnboardingController::class, 'store'])->middleware('can:create,'.Onboarding::class)->name('store');

    Route::prefix('tasks/{onboardingTask}')->name('tasks.')->group(function () {
        Route::post('/start', [OnboardingTaskController::class, 'start'])->middleware('can:act,onboardingTask')->name('start');
        Route::post('/complete', [OnboardingTaskController::class, 'complete'])->middleware('can:act,onboardingTask')->name('complete');
        Route::post('/verify', [OnboardingTaskController::class, 'verify'])->middleware('can:verify,onboardingTask')->name('verify');
        Route::post('/skip', [OnboardingTaskController::class, 'skip'])->middleware('can:skip,onboardingTask')->name('skip');
    });

    Route::prefix('{onboarding}')->group(function () {
        Route::get('/', [OnboardingController::class, 'show'])->middleware('can:view,onboarding')->name('show');
        Route::post('/complete', [OnboardingController::class, 'complete'])->middleware('can:complete,onboarding')->name('complete');
        Route::post('/cancel', [OnboardingController::class, 'cancel'])->middleware('can:cancel,onboarding')->name('cancel');
        Route::post('/tasks', [OnboardingController::class, 'storeTask'])->middleware('can:update,onboarding')->name('tasks.store');
        Route::post('/notes', [OnboardingController::class, 'storeNote'])->middleware('can:update,onboarding')->name('notes.store');
    });
});

Route::middleware(['auth', 'verified'])->prefix('people/onboarding-templates')->name('people.onboarding-templates.')->group(function () {
    Route::get('/', [OnboardingTemplateController::class, 'index'])->middleware('can:viewAny,'.OnboardingTemplate::class)->name('index');
    Route::get('/create', [OnboardingTemplateController::class, 'create'])->middleware('can:create,'.OnboardingTemplate::class)->name('create');
    Route::post('/', [OnboardingTemplateController::class, 'store'])->middleware('can:create,'.OnboardingTemplate::class)->name('store');
    Route::get('/{onboardingTemplate}/edit', [OnboardingTemplateController::class, 'edit'])->middleware('can:update,onboardingTemplate')->name('edit');
    Route::put('/{onboardingTemplate}', [OnboardingTemplateController::class, 'update'])->middleware('can:update,onboardingTemplate')->name('update');
});

// Employee self-service: own onboarding, and tasks assigned to me.
Route::middleware(['auth', 'verified'])
    ->get('people/my-onboarding', [MyOnboardingController::class, 'index'])
    ->middleware('can:viewMine,'.Onboarding::class)
    ->name('people.my-onboarding.index');
