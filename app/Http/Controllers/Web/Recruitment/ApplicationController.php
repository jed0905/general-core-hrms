<?php

namespace App\Http\Controllers\Web\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\AttachApplicationDocumentsRequest;
use App\Http\Requests\Recruitment\MoveApplicationRequest;
use App\Http\Requests\Recruitment\RecordScreeningRequest;
use App\Http\Requests\Recruitment\RejectApplicationRequest;
use App\Http\Requests\Recruitment\StoreApplicationNoteRequest;
use App\Http\Requests\Recruitment\StoreApplicationRequest;
use App\Http\Requests\Recruitment\WithdrawApplicationRequest;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\ApplicationEvaluation;
use App\Models\ApplicationSelection;
use App\Models\AssessmentType;
use App\Models\InterviewType;
use App\Models\JobOffer;
use App\Models\Onboarding;
use App\Models\RecruitmentStage;
use App\Models\RejectionReason;
use App\Models\Vacancy;
use App\Models\VacancyStage;
use App\Services\Recruitment\ApplicationConversionService;
use App\Services\Recruitment\ApplicationPipelineService;
use App\Services\Recruitment\ApplicationService;
use App\Services\Recruitment\EvaluationService;
use App\Services\Recruitment\RecruitmentFormOptions;
use App\Services\Recruitment\ScreeningService;
use App\Services\Recruitment\SelectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationController extends Controller
{
    public function __construct(
        protected ApplicationService $applicationService,
        protected ApplicationPipelineService $pipeline,
        protected ScreeningService $screening,
        protected RecruitmentFormOptions $options,
        protected SelectionService $selections,
        protected EvaluationService $evaluations,
        protected ApplicationConversionService $conversions
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'vacancy_id', 'status', 'stage_type']);
        $user = $request->user();

        return Inertia::render('app/Recruitment/Applications/Index', [
            'applications' => $this->applicationService->getPaginatedApplications($user, $filters),
            'filters' => $filters,
            'vacancies' => Vacancy::whereIn('id', $this->applicationService->visibleTo($user)->select('vacancy_id'))
                ->orderBy('title')->get(['id', 'vacancy_number', 'title']),
            'statuses' => Application::STATUSES,
            'stageTypes' => RecruitmentStage::TYPES,
        ]);
    }

    public function store(StoreApplicationRequest $request): RedirectResponse
    {
        $application = $this->applicationService->createApplication(
            Applicant::findOrFail($request->integer('applicant_id')),
            Vacancy::findOrFail($request->integer('vacancy_id')),
            $request->safe()->only(['recruitment_source_id', 'document_ids', 'remarks']),
            $request->user()
        );

        return redirect()->route('recruitment.applications.show', $application)->with('success', 'Application created.');
    }

    public function show(Request $request, Application $application): Response
    {
        $user = $request->user();
        $application->load([
            'applicant:id,applicant_number,first_name,middle_name,last_name,suffix,preferred_name,email,phone,alternate_phone,is_internal',
            'vacancy:id,vacancy_number,title,status,department_id,hiring_manager_id,job_title_id,employment_status_id,location_id,salary_currency,openings,filled_count', 'vacancy.department:id,name',
            'currentStage', 'source:id,name', 'rejectionReason:id,name',
            'history' => fn ($q) => $q->with(['actor:id,username', 'rejectionReason:id,name']),
            'documents' => fn ($q) => $q->with('type:id,name'),
            'notes',
        ]);

        $canScreen = $user->can('screen', $application);
        $current = $application->currentStage;
        $next = VacancyStage::where('vacancy_id', $application->vacancy_id)->where('sort_order', '>', $current->sort_order)->orderBy('sort_order')->first();
        $canAttach = $user->can('attachDocuments', $application);
        $canAdvance = $user->can('advance', $application) && $next && $next->stage_type !== RecruitmentStage::TYPE_SHORTLISTED;
        $postShortlist = in_array($current->stage_type, RecruitmentStage::POST_SHORTLIST_TYPES, true);
        $canSchedule = $postShortlist && $user->can('scheduleInterview', $application);
        $canCreateAssessment = $current->stage_type === RecruitmentStage::TYPE_ASSESSMENT && $user->can('createAssessment', $application);
        $canViewSelection = $user->can('viewAny', [ApplicationSelection::class, $application]);
        $currentSelection = $this->selections->current($application);
        $inEvaluation = $current->stage_type === RecruitmentStage::TYPE_EVALUATION;
        $hasActiveOffer = $application->offers()->whereIn('status', JobOffer::ACTIVE)->exists();
        $canCreateOffer = $currentSelection?->isSelected() && ! $hasActiveOffer && $user->can('create', [JobOffer::class, $application]);

        return Inertia::render('app/Recruitment/Applications/Show', [
            'application' => $application,
            'stages' => VacancyStage::where('vacancy_id', $application->vacancy_id)->orderBy('sort_order')->get(['id', 'name', 'stage_type', 'sort_order']),
            'nextStage' => $next?->only(['id', 'name', 'stage_type']),
            'screening' => $user->can('viewScreening', $application)
                ? $application->screening()->with(['screener:id,username', 'rejectionReason:id,name'])->first()
                : null,
            'rejectionReasons' => RejectionReason::active()->get(['id', 'name']),
            'attachableDocuments' => $canAttach
                ? $application->applicant->documents()->with('type:id,name')->whereNotIn('id', $application->documents->pluck('id'))->get(['id', 'original_name', 'applicant_document_type_id'])
                : [],
            'nextStageBlocker' => $canAdvance ? $this->pipeline->prerequisiteProblem($application, $current, $next) : null,
            'interviews' => $user->can('viewInterviews', $application)
                ? $application->interviews()->with(['type:id,name', 'panelists.employee:id,emp_first_name,emp_last_name'])
                    ->withCount(['evaluations as submitted_evaluations_count' => fn ($q) => $q->where('status', ApplicationEvaluation::STATUS_SUBMITTED)])
                    ->get()
                : null,
            'assessments' => $user->can('viewAssessments', $application)
                ? $application->assessments()->with(['type:id,name', 'assessor:id,emp_first_name,emp_last_name', 'completer:id,username', 'canceller:id,username'])->get()
                    ->each(fn ($a) => $a->setAttribute('can_update', $user->can('update', $a)))
                : null,
            'evaluations' => $user->can('viewEvaluations', $application)
                ? $application->evaluations()->where('status', ApplicationEvaluation::STATUS_SUBMITTED)
                    ->with(['scores', 'evaluator:id,emp_first_name,emp_last_name', 'interview:id,round,interview_type_id', 'interview.type:id,name'])->get()
                : null,
            'selection' => $canViewSelection ? [
                'history' => $application->selections()->with('decider:id,username')->get(),
                'current' => $currentSelection?->only(['id', 'decision', 'decided_at', 'remarks', 'is_automatic']),
                'summary' => $inEvaluation || $currentSelection ? $this->evaluations->summary($application) : null,
                'evaluationProblem' => $inEvaluation ? $this->evaluations->completionProblem($application) : null,
                'eligibilityProblem' => $this->selections->eligibilityProblem($application),
                'vacancy' => $application->vacancy->only(['openings', 'filled_count', 'remaining_openings']),
            ] : null,
            'offers' => $user->can('viewForApplication', [JobOffer::class, $application])
                ? $application->offers()->get(['id', 'offer_number', 'status', 'position_title', 'base_salary', 'salary_frequency', 'currency', 'proposed_start_date', 'expiry_date', 'issued_at', 'responded_at', 'created_at'])
                : null,
            'offerOptions' => $canCreateOffer ? $this->options->offerTerms() + ['defaults' => [
                'job_title_id' => $application->vacancy->job_title_id,
                'employment_status_id' => $application->vacancy->employment_status_id,
                'location_id' => $application->vacancy->location_id,
                'currency' => $application->vacancy->salary_currency ?: 'PHP',
            ]] : null,
            'conversion' => $user->can('viewConversion', $application) ? $this->conversionData($application, $user) : null,
            'interviewTypes' => $canSchedule ? InterviewType::active()->get(['id', 'name']) : [],
            'assessmentTypes' => $canCreateAssessment || $user->can('recruitment.assessment.update') ? AssessmentType::active()->get(['id', 'name', 'result_type']) : [],
            'employees' => $canSchedule || $user->can('recruitment.assessment.create') || $user->can('recruitment.assessment.update') ? $this->options->employees() : [],
            'can' => [
                'viewApplicant' => $user->can('view', $application->applicant),
                'advance' => $canAdvance,
                'scheduleInterview' => $canSchedule,
                'decideSelection' => $inEvaluation && $user->can('create', [ApplicationSelection::class, $application]),
                'createOffer' => (bool) $canCreateOffer,
                'createAssessment' => $canCreateAssessment,
                'shortlist' => $user->can('shortlist', $application) && $next?->stage_type === RecruitmentStage::TYPE_SHORTLISTED,
                'screen' => $canScreen && $current->stage_type === RecruitmentStage::TYPE_SCREENING,
                'viewScreening' => $user->can('viewScreening', $application),
                'reject' => $user->can('reject', $application),
                'withdraw' => $user->can('withdraw', $application),
                'attachDocuments' => $canAttach,
                'addNote' => $user->can('addNote', $application),
            ],
        ]);
    }

    /**
     * Eligibility before conversion; the recorded hand-off after it.
     */
    protected function conversionData(Application $application, $user): ?array
    {
        $record = $application->conversion()->with([
            'employee:id,employee_number,emp_first_name,emp_last_name,status',
            'movement:id,movement_type_id,effective_date,status', 'movement.type:id,name',
            'user:id,username', 'converter:id,username', 'offer:id,offer_number',
        ])->first();

        if ($record) {
            $onboarding = Onboarding::where('employee_id', $record->employee_id)->latest('id')->first(['id', 'status']);

            return [
                'record' => $record,
                'canViewEmployee' => $user->can('view', $record->employee),
                // Phase 6 hand-off: onboarding is started explicitly by HR (never inside the conversion).
                'onboarding' => $onboarding,
                'canStartOnboarding' => $user->can('create', Onboarding::class) && ! ($onboarding && $onboarding->isActive()),
                'canViewOnboarding' => $onboarding && $user->can('view', $onboarding),
            ];
        }

        $eligibility = $this->conversions->eligibility($application);
        if (! $eligibility['offer'] && ! $application->offers()->exists()) {
            return null; // nothing to show before there is an offer
        }

        $canConvert = $eligibility['problem'] === null && $user->can('convert', $application);
        $existing = $eligibility['existingEmployee'];

        return [
            'record' => null,
            'problem' => $eligibility['problem'],
            'offer' => $eligibility['offer']?->only(['id', 'offer_number', 'status', 'position_title', 'department_name', 'employment_type', 'work_location', 'proposed_start_date', 'responded_at']),
            'existingEmployee' => $existing ? [
                'id' => $existing->id,
                'employee_number' => $existing->employee_number,
                'name' => trim("{$existing->emp_first_name} {$existing->emp_last_name}"),
                'status' => $existing->status,
                'hasAccount' => $existing->user()->exists(),
                'canView' => $user->can('view', $existing),
            ] : null,
            'documents' => $eligibility['documents']->map(fn ($d) => ['id' => $d->id, 'name' => $d->original_name, 'type' => $d->type?->name])->values(),
            'supervisors' => $canConvert && ! $existing ? $this->options->employees() : [],
            'defaultSupervisorId' => $application->vacancy->hiring_manager_id,
            'canConvert' => $canConvert,
            'canCreateAccount' => $canConvert && ! $existing && $user->can('user.create'),
        ];
    }

    public function advance(MoveApplicationRequest $request, Application $application): RedirectResponse
    {
        $this->pipeline->advance($application, $request->integer('expected_stage_id'), $request->user(), $request->validated('remarks'));

        return back()->with('success', 'Application moved to the next stage.');
    }

    public function shortlist(MoveApplicationRequest $request, Application $application): RedirectResponse
    {
        $this->pipeline->shortlist($application, $request->integer('expected_stage_id'), $request->user(), $request->validated('remarks'));

        return back()->with('success', 'Application shortlisted.');
    }

    public function reject(RejectApplicationRequest $request, Application $application): RedirectResponse
    {
        $this->pipeline->reject($application, $request->integer('rejection_reason_id'), $request->user(), $request->validated('remarks'));

        return back()->with('success', 'Application rejected.');
    }

    public function withdraw(WithdrawApplicationRequest $request, Application $application): RedirectResponse
    {
        $this->pipeline->withdraw($application, $request->validated('reason'), $request->user());

        return back()->with('success', 'Application withdrawn.');
    }

    public function screening(RecordScreeningRequest $request, Application $application): RedirectResponse
    {
        $this->screening->record($application, $request->validated(), $request->user());

        return back()->with('success', 'Screening recorded.');
    }

    public function attachDocuments(AttachApplicationDocumentsRequest $request, Application $application): RedirectResponse
    {
        $this->applicationService->attachDocuments($application, $request->validated('document_ids'), $request->user());

        return back()->with('success', 'Documents attached.');
    }

    public function storeNote(StoreApplicationNoteRequest $request, Application $application): RedirectResponse
    {
        $this->applicationService->addNote($application, $request->validated('body'), $request->user());

        return back()->with('success', 'Note added.');
    }
}
