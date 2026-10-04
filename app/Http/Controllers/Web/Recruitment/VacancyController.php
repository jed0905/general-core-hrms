<?php

namespace App\Http\Controllers\Web\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\StoreVacancyRequest;
use App\Http\Requests\Recruitment\UpdateVacancyRequest;
use App\Http\Requests\Recruitment\VacancyStatusRequest;
use App\Models\Application;
use App\Models\JobRequisition;
use App\Models\Vacancy;
use App\Services\Recruitment\ApplicationService;
use App\Services\Recruitment\RecruitmentFormOptions;
use App\Services\Recruitment\SelectionService;
use App\Services\Recruitment\VacancyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VacancyController extends Controller
{
    public function __construct(
        protected VacancyService $vacancyService,
        protected RecruitmentFormOptions $options,
        protected ApplicationService $applications,
        protected SelectionService $selections
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['status', 'department_id', 'search']);

        return Inertia::render('app/Recruitment/Vacancies/Index', [
            'vacancies' => $this->vacancyService->getPaginatedVacancies($request->user(), $filters),
            'filters' => $filters,
            'statuses' => Vacancy::STATUSES,
            'departments' => $this->options->all()['departments'],
            'can' => ['create' => $request->user()->can('create', Vacancy::class)],
        ]);
    }

    /**
     * ?requisition={id} prefills the form from an approved requisition.
     */
    public function create(Request $request): Response
    {
        $requisition = null;

        if ($request->filled('requisition')) {
            $requisition = JobRequisition::with(['jobTitle:id,job_title'])->findOrFail($request->integer('requisition'));
            $this->authorize('view', $requisition);
            abort_unless($requisition->isApproved(), 422, 'Vacancies can only be created from an approved requisition.');
        }

        return Inertia::render('app/Recruitment/Vacancies/Form', [
            'vacancy' => null,
            'requisition' => $requisition ? $requisition->only(['id', 'requisition_number', 'positions', 'department_id', 'job_title_id', 'location_id', 'employment_status_id']) + [
                'remaining' => $requisition->positions - $requisition->allocatedOpenings(),
                'job_title' => $requisition->jobTitle?->job_title,
            ] : null,
            'options' => $this->options->all(),
            'visibilities' => Vacancy::VISIBILITIES,
            'contentOnly' => false,
        ]);
    }

    public function store(StoreVacancyRequest $request): RedirectResponse
    {
        $vacancy = $this->vacancyService->createVacancy($request->validated(), $request->user());

        return redirect()->route('recruitment.vacancies.show', $vacancy)->with('success', 'Vacancy saved as a draft.');
    }

    public function show(Request $request, Vacancy $vacancy): Response
    {
        $user = $request->user();
        $vacancy->load([
            'department:id,name', 'jobTitle:id,job_title', 'location:id,address,city', 'employmentStatus:id,name',
            'hiringManager:id,employee_number,emp_first_name,emp_last_name',
            'requisition:id,requisition_number,status,positions', 'creator:id,username',
        ]);

        // The ATS workspace: org-wide application access, or this vacancy's hiring manager.
        $canSeePipeline = $user->can('recruitment.application.view')
            || ($user->employee_id !== null && $vacancy->hiring_manager_id === $user->employee_id);
        $pipeline = $canSeePipeline ? $this->applications->pipeline($vacancy) : null;
        $selected = $request->input('stage', $pipeline['stages'][0]['id'] ?? null);

        return Inertia::render('app/Recruitment/Vacancies/Show', [
            'pipeline' => $pipeline,
            'selectedStage' => $selected,
            'stageApplications' => $pipeline && $selected
                ? Application::where('vacancy_id', $vacancy->id)
                    ->when(in_array($selected, Application::TERMINAL, true),
                        fn ($q) => $q->where('status', $selected),
                        fn ($q) => $q->where('current_vacancy_stage_id', (int) $selected)->whereIn('status', [Application::STATUS_ACTIVE, Application::STATUS_SHORTLISTED]))
                    ->with(['applicant:id,applicant_number,first_name,last_name,email,phone', 'currentStage:id,name', 'screening:id,application_id,result', 'currentSelection'])
                    ->orderBy('applied_at')
                    ->paginate(20, ['*'], 'page')
                    ->withQueryString()
                : null,
            'selectionSummary' => $canSeePipeline ? $this->selections->vacancySummary($vacancy) : null,
            'vacancy' => $vacancy,
            'publication' => [
                'eligible' => in_array($vacancy->visibility, Vacancy::PUBLIC_VISIBILITIES, true) && ! $vacancy->isTerminal(),
                'published_at' => $vacancy->published_at?->toIso8601String(),
                'show_salary_publicly' => $vacancy->show_salary_publicly,
                'live' => $vacancy->isPubliclyOpen(),
                'url' => $vacancy->public_slug ? route('careers.jobs.show', $vacancy->public_slug) : null,
            ],
            'transitions' => Vacancy::TRANSITIONS[$vacancy->status] ?? [],
            'can' => [
                'update' => $user->can('update', $vacancy),
                'publish' => $user->can('publish', $vacancy),
                'close' => $user->can('close', $vacancy),
                'viewRequisition' => $vacancy->requisition && $user->can('view', $vacancy->requisition),
                'addApplicant' => $vacancy->status === Vacancy::STATUS_OPEN && $user->can('create', Application::class) && $user->can('recruitment.applicant.view'),
            ],
        ]);
    }

    public function edit(Request $request, Vacancy $vacancy): Response
    {
        return Inertia::render('app/Recruitment/Vacancies/Form', [
            'vacancy' => $vacancy->load('requisition:id,requisition_number,positions'),
            'requisition' => null,
            'options' => $this->options->all(),
            'visibilities' => Vacancy::VISIBILITIES,
            'contentOnly' => ! $request->user()->can('recruitment.vacancy.update'),
        ]);
    }

    public function update(UpdateVacancyRequest $request, Vacancy $vacancy): RedirectResponse
    {
        $this->vacancyService->updateVacancy($vacancy, $request->validated(), $request->contentOnly());

        return redirect()->route('recruitment.vacancies.show', $vacancy)->with('success', 'Vacancy updated.');
    }

    public function changeStatus(VacancyStatusRequest $request, Vacancy $vacancy): RedirectResponse
    {
        $this->vacancyService->transition($vacancy, $request->targetStatus(), $request->user(), $request->validated('reason'));

        return back()->with('success', 'Vacancy status updated.');
    }
}
