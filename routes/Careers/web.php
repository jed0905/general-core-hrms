<?php

use App\Http\Controllers\Careers\ApplicantAuthController;
use App\Http\Controllers\Careers\ApplicantPortalController;
use App\Http\Controllers\Careers\CareersController;
use App\Http\Controllers\Careers\PublicApplicationController;
use App\Http\Middleware\Careers\AuthenticateApplicant;
use App\Http\Middleware\Careers\ShareCareersPortal;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public careers portal (recruitment phase 7)
|--------------------------------------------------------------------------
| Loaded with the "web" group only (sessions + CSRF), never "auth": employees
| and HR sign in elsewhere. Candidates use the separate "applicant" guard;
| internal recruitment routes never accept it. Vacancies are addressed by
| their public slug, never by id.
*/
Route::prefix('careers')->name('careers.')->middleware(ShareCareersPortal::class)->group(function () {
    Route::middleware('throttle:careers-browse')->group(function () {
        Route::get('/', [CareersController::class, 'index'])->name('index');
        Route::get('/jobs/{slug}', [CareersController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('jobs.show');

        Route::get('/register', [ApplicantAuthController::class, 'showRegister'])->name('register');
        Route::get('/register/complete/{account}/{hash}', [ApplicantAuthController::class, 'showComplete'])->middleware('signed')->name('register.complete');
        Route::get('/login', [ApplicantAuthController::class, 'showLogin'])->name('login');
        Route::get('/forgot-password', [ApplicantAuthController::class, 'showForgot'])->name('password.request');
        Route::get('/reset-password/{token}', [ApplicantAuthController::class, 'showReset'])->name('password.reset');
    });

    Route::middleware('throttle:careers-auth')->group(function () {
        Route::post('/register', [ApplicantAuthController::class, 'register'])->name('register.store');
        Route::post('/register/resend', [ApplicantAuthController::class, 'resend'])->name('register.resend');
        Route::post('/register/complete/{account}/{hash}', [ApplicantAuthController::class, 'complete'])->middleware('signed')->name('register.complete.store');
        Route::post('/login', [ApplicantAuthController::class, 'login'])->name('login.store');
        Route::post('/forgot-password', [ApplicantAuthController::class, 'sendReset'])->name('password.email');
        Route::post('/reset-password', [ApplicantAuthController::class, 'reset'])->name('password.update');
    });

    // Signed-in candidate only: everything is scoped to their own applicant record.
    Route::middleware(AuthenticateApplicant::class)->group(function () {
        Route::post('/logout', [ApplicantAuthController::class, 'logout'])->name('logout');

        Route::middleware('throttle:careers-browse')->group(function () {
            Route::get('/jobs/{slug}/apply', [PublicApplicationController::class, 'create'])->where('slug', '[a-z0-9-]+')->name('jobs.apply');
            Route::get('/my-applications', [ApplicantPortalController::class, 'index'])->name('applications.index');
            Route::get('/my-applications/{number}', [ApplicantPortalController::class, 'show'])->name('applications.show');
            Route::get('/profile', [ApplicantPortalController::class, 'profile'])->name('profile');
            Route::get('/documents/{document}/download', [ApplicantPortalController::class, 'downloadDocument'])->whereNumber('document')->name('documents.download');
        });

        Route::middleware('throttle:careers-apply')->group(function () {
            Route::post('/jobs/{slug}/apply', [PublicApplicationController::class, 'store'])->where('slug', '[a-z0-9-]+')->name('jobs.apply.store');
            Route::post('/my-applications/{number}/withdraw', [ApplicantPortalController::class, 'withdraw'])->name('applications.withdraw');
            Route::put('/profile', [ApplicantPortalController::class, 'updateProfile'])->name('profile.update');
            Route::post('/documents', [ApplicantPortalController::class, 'uploadDocument'])->name('documents.store');
            Route::delete('/documents/{document}', [ApplicantPortalController::class, 'deleteDocument'])->whereNumber('document')->name('documents.destroy');
        });
    });
});
