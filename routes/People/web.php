<?php

use App\Http\Controllers\Web\People\EmployeeCompensationController;
use App\Http\Controllers\Web\People\EmployeeContactController;
use App\Http\Controllers\Web\People\EmployeeController;
use App\Http\Controllers\Web\People\EmployeeDocumentController;
use App\Http\Controllers\Web\People\EmployeeEducationController;
use App\Http\Controllers\Web\People\EmployeeEmploymentController;
use App\Http\Controllers\Web\People\EmployeeExperienceController;
use App\Http\Controllers\Web\People\EmployeePersonalController;
use App\Http\Controllers\Web\People\EmploymentStatusController;
use App\Http\Controllers\Web\People\JobTitleController;
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
