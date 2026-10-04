<?php

use App\Http\Controllers\Web\Recruitment\ApplicantController;
use App\Http\Controllers\Web\Recruitment\ApplicantDocumentController;
use App\Http\Controllers\Web\Recruitment\ApplicationController;
use App\Http\Controllers\Web\Recruitment\ApplicationConversionController;
use App\Http\Controllers\Web\Recruitment\AssessmentController;
use App\Http\Controllers\Web\Recruitment\InterviewController;
use App\Http\Controllers\Web\Recruitment\JobRequisitionController;
use App\Http\Controllers\Web\Recruitment\OfferController;
use App\Http\Controllers\Web\Recruitment\RecruitmentDashboardController;
use App\Http\Controllers\Web\Recruitment\RecruitmentSettingsController;
use App\Http\Controllers\Web\Recruitment\SelectionController;
use App\Http\Controllers\Web\Recruitment\VacancyController;
use App\Http\Controllers\Web\Recruitment\VacancyPublicationController;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\ApplicationInterview;
use App\Models\ApplicationSelection;
use App\Models\JobOffer;
use App\Models\JobRequisition;
use App\Models\Vacancy;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Talent & Recruitment
|--------------------------------------------------------------------------
| Every route is authorized through a policy (permissions plus assignments:
| requester, approver, hiring manager). Role names are never checked.
*/

Route::middleware(['auth', 'verified'])->prefix('recruitment')->name('recruitment.')->group(function () {
    Route::get('/', [RecruitmentDashboardController::class, 'index'])
        ->middleware('can:accessRecruitment')
        ->name('dashboard');

    // Job requisitions
    Route::prefix('requisitions')->name('requisitions.')->group(function () {
        Route::get('/', [JobRequisitionController::class, 'index'])->middleware('can:viewAny,'.JobRequisition::class)->name('index');
        Route::get('/create', [JobRequisitionController::class, 'create'])->middleware('can:create,'.JobRequisition::class)->name('create');
        Route::post('/', [JobRequisitionController::class, 'store'])->middleware('can:create,'.JobRequisition::class)->name('store');

        Route::prefix('{requisition}')->group(function () {
            Route::get('/', [JobRequisitionController::class, 'show'])->middleware('can:view,requisition')->name('show');
            Route::get('/edit', [JobRequisitionController::class, 'edit'])->middleware('can:update,requisition')->name('edit');
            Route::put('/', [JobRequisitionController::class, 'update'])->middleware('can:update,requisition')->name('update');
            Route::post('/submit', [JobRequisitionController::class, 'submit'])->middleware('can:submit,requisition')->name('submit');
            Route::post('/approve', [JobRequisitionController::class, 'approve'])->middleware('can:approve,requisition')->name('approve');
            Route::post('/reject', [JobRequisitionController::class, 'reject'])->middleware('can:reject,requisition')->name('reject');
            Route::post('/cancel', [JobRequisitionController::class, 'cancel'])->middleware('can:cancel,requisition')->name('cancel');
        });
    });

    // Vacancies
    Route::prefix('vacancies')->name('vacancies.')->group(function () {
        Route::get('/', [VacancyController::class, 'index'])->middleware('can:viewAny,'.Vacancy::class)->name('index');
        Route::get('/create', [VacancyController::class, 'create'])->middleware('can:create,'.Vacancy::class)->name('create');
        Route::post('/', [VacancyController::class, 'store'])->middleware('can:create,'.Vacancy::class)->name('store');

        Route::prefix('{vacancy}')->group(function () {
            Route::get('/', [VacancyController::class, 'show'])->middleware('can:view,vacancy')->name('show');
            Route::get('/edit', [VacancyController::class, 'edit'])->middleware('can:update,vacancy')->name('edit');
            Route::put('/', [VacancyController::class, 'update'])->middleware('can:update,vacancy')->name('update');

            // Careers site: publish / unpublish / preview (same "publish" capability as opening).
            Route::post('/publish-online', [VacancyPublicationController::class, 'publish'])->middleware('can:publish,vacancy')->name('publish-online');
            Route::post('/unpublish-online', [VacancyPublicationController::class, 'unpublish'])->middleware('can:publish,vacancy')->name('unpublish-online');
            Route::get('/public-preview', [VacancyPublicationController::class, 'preview'])->middleware('can:view,vacancy')->name('public-preview');

            // Status actions: open/hold/reopen need "publish"; close/fill/cancel need "close".
            foreach (['open' => 'publish', 'hold' => 'publish', 'reopen' => 'publish', 'close' => 'close', 'fill' => 'close', 'cancel' => 'close'] as $action => $ability) {
                Route::post("/{$action}", [VacancyController::class, 'changeStatus'])
                    ->defaults('action', $action)
                    ->middleware("can:{$ability},vacancy")
                    ->name($action);
            }
        });
    });

    // Applicants (the person) and their private documents
    Route::prefix('applicants')->name('applicants.')->group(function () {
        Route::get('/', [ApplicantController::class, 'index'])->middleware('can:viewAny,'.Applicant::class)->name('index');
        Route::get('/create', [ApplicantController::class, 'create'])->middleware('can:create,'.Applicant::class)->name('create');
        Route::post('/', [ApplicantController::class, 'store'])->middleware('can:create,'.Applicant::class)->name('store');

        Route::prefix('{applicant}')->group(function () {
            Route::get('/', [ApplicantController::class, 'show'])->middleware('can:view,applicant')->name('show');
            Route::get('/edit', [ApplicantController::class, 'edit'])->middleware('can:update,applicant')->name('edit');
            Route::put('/', [ApplicantController::class, 'update'])->middleware('can:update,applicant')->name('update');

            Route::prefix('documents')->name('documents.')->scopeBindings()->group(function () {
                Route::post('/', [ApplicantDocumentController::class, 'store'])->middleware('can:manageDocuments,applicant')->name('store');
                Route::delete('/{document}', [ApplicantDocumentController::class, 'destroy'])->middleware('can:delete,document')->name('destroy');
                Route::get('/{document}/download', [ApplicantDocumentController::class, 'download'])->middleware('can:view,document')->name('download');
                Route::get('/{document}/view', [ApplicantDocumentController::class, 'view'])->middleware('can:view,document')->name('view');
            });
        });
    });

    // Applications (applicant x vacancy) and the pipeline: every stage/status change goes through ApplicationPipelineService.
    Route::prefix('applications')->name('applications.')->group(function () {
        Route::get('/', [ApplicationController::class, 'index'])->middleware('can:viewAny,'.Application::class)->name('index');
        Route::post('/', [ApplicationController::class, 'store'])->middleware('can:create,'.Application::class)->name('store');

        Route::prefix('{application}')->group(function () {
            Route::get('/', [ApplicationController::class, 'show'])->middleware('can:view,application')->name('show');
            Route::post('/advance', [ApplicationController::class, 'advance'])->middleware('can:advance,application')->name('advance');
            Route::post('/shortlist', [ApplicationController::class, 'shortlist'])->middleware('can:shortlist,application')->name('shortlist');
            Route::post('/reject', [ApplicationController::class, 'reject'])->middleware('can:reject,application')->name('reject');
            Route::post('/withdraw', [ApplicationController::class, 'withdraw'])->middleware('can:withdraw,application')->name('withdraw');
            Route::put('/screening', [ApplicationController::class, 'screening'])->middleware('can:screen,application')->name('screening');
            Route::post('/documents', [ApplicationController::class, 'attachDocuments'])->middleware('can:attachDocuments,application')->name('documents.attach');
            Route::post('/notes', [ApplicationController::class, 'storeNote'])->middleware('can:addNote,application')->name('notes.store');
            Route::post('/interviews', [InterviewController::class, 'store'])->middleware('can:scheduleInterview,application')->name('interviews.store');
            Route::post('/assessments', [AssessmentController::class, 'store'])->middleware('can:createAssessment,application')->name('assessments.store');
            Route::post('/selection', [SelectionController::class, 'store'])->middleware('can:create,'.ApplicationSelection::class.',application')->name('selection.store');
            Route::post('/offers', [OfferController::class, 'store'])->middleware('can:create,'.JobOffer::class.',application')->name('offers.store');
            // Hand-off to Core HR (accepted offer → employee); never public.
            Route::post('/convert', [ApplicationConversionController::class, 'store'])->middleware('can:convert,application')->name('convert');
        });
    });

    // Interviews: HR manages them; the vacancy's hiring manager views them; assigned
    // panelists see their own interviews and write only their own scorecard.
    Route::get('/my-interviews', [InterviewController::class, 'mine'])->middleware('can:viewMine,'.ApplicationInterview::class)->name('interviews.mine');
    Route::prefix('interviews/{interview}')->name('interviews.')->group(function () {
        Route::get('/', [InterviewController::class, 'show'])->middleware('can:view,interview')->name('show');
        Route::put('/', [InterviewController::class, 'update'])->middleware('can:update,interview')->name('update');
        Route::post('/reschedule', [InterviewController::class, 'reschedule'])->middleware('can:update,interview')->name('reschedule');
        Route::post('/cancel', [InterviewController::class, 'cancel'])->middleware('can:update,interview')->name('cancel');
        Route::post('/complete', [InterviewController::class, 'complete'])->middleware('can:update,interview')->name('complete');
        Route::put('/evaluation', [InterviewController::class, 'saveEvaluation'])->middleware('can:evaluate,interview')->name('evaluation.save');
        Route::post('/evaluation/submit', [InterviewController::class, 'submitEvaluation'])->middleware('can:evaluate,interview')->name('evaluation.submit');
    });

    // Assessments (managed from the application page)
    Route::prefix('assessments/{assessment}')->name('assessments.')->group(function () {
        Route::put('/', [AssessmentController::class, 'update'])->middleware('can:update,assessment')->name('update');
        Route::post('/start', [AssessmentController::class, 'start'])->middleware('can:update,assessment')->name('start');
        Route::post('/complete', [AssessmentController::class, 'complete'])->middleware('can:update,assessment')->name('complete');
        Route::post('/cancel', [AssessmentController::class, 'cancel'])->middleware('can:update,assessment')->name('cancel');
    });

    // Job offers: HR prepares, issues and records the candidate's answer; approvers decide
    // their own step; the vacancy's hiring manager views. No public routes.
    Route::prefix('offers')->name('offers.')->group(function () {
        Route::get('/', [OfferController::class, 'index'])->middleware('can:viewAny,'.JobOffer::class)->name('index');

        Route::prefix('{offer}')->group(function () {
            Route::get('/', [OfferController::class, 'show'])->middleware('can:view,offer')->name('show');
            Route::put('/', [OfferController::class, 'update'])->middleware('can:update,offer')->name('update');
            Route::post('/submit', [OfferController::class, 'submit'])->middleware('can:update,offer')->name('submit');
            Route::post('/approve', [OfferController::class, 'approve'])->middleware('can:approve,offer')->name('approve');
            Route::post('/reject', [OfferController::class, 'reject'])->middleware('can:reject,offer')->name('reject');
            Route::post('/issue', [OfferController::class, 'issue'])->middleware('can:issue,offer')->name('issue');
            Route::post('/respond', [OfferController::class, 'respond'])->middleware('can:respond,offer')->name('respond');
            Route::post('/withdraw', [OfferController::class, 'withdraw'])->middleware('can:withdraw,offer')->name('withdraw');
        });
    });

    // Settings: stage names and recruitment lookups
    Route::prefix('settings')->name('settings.')->middleware('can:recruitment.config.manage')->group(function () {
        Route::get('/', [RecruitmentSettingsController::class, 'index'])->name('index');
        Route::put('/stages/{stage}', [RecruitmentSettingsController::class, 'updateStage'])->name('stages.update');
        Route::post('/sources', [RecruitmentSettingsController::class, 'storeSource'])->name('sources.store');
        Route::put('/sources/{source}', [RecruitmentSettingsController::class, 'updateSource'])->name('sources.update');
        Route::post('/reasons', [RecruitmentSettingsController::class, 'storeReason'])->name('reasons.store');
        Route::put('/reasons/{reason}', [RecruitmentSettingsController::class, 'updateReason'])->name('reasons.update');
        Route::post('/interview-types', [RecruitmentSettingsController::class, 'storeInterviewType'])->name('interview-types.store');
        Route::put('/interview-types/{interviewType}', [RecruitmentSettingsController::class, 'updateInterviewType'])->name('interview-types.update');
        Route::post('/assessment-types', [RecruitmentSettingsController::class, 'storeAssessmentType'])->name('assessment-types.store');
        Route::put('/assessment-types/{assessmentType}', [RecruitmentSettingsController::class, 'updateAssessmentType'])->name('assessment-types.update');
        Route::post('/criteria', [RecruitmentSettingsController::class, 'storeCriterion'])->name('criteria.store');
        Route::put('/criteria/{criterion}', [RecruitmentSettingsController::class, 'updateCriterion'])->name('criteria.update');
        Route::put('/careers', [RecruitmentSettingsController::class, 'updateCareers'])->name('careers.update');
        Route::put('/document-types/{documentType}', [RecruitmentSettingsController::class, 'updateDocumentType'])->name('document-types.update');
    });
});
