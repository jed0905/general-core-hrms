<?php

namespace App\Http\Controllers\Web\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\StoreApplicantRequest;
use App\Http\Requests\Recruitment\UpdateApplicantRequest;
use App\Models\Applicant;
use App\Models\ApplicantDocumentType;
use App\Models\Application;
use App\Models\RecruitmentSource;
use App\Models\Vacancy;
use App\Services\Recruitment\ApplicantService;
use App\Services\Recruitment\RecruitmentFormOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApplicantController extends Controller
{
    public function __construct(
        protected ApplicantService $applicantService,
        protected RecruitmentFormOptions $options
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status', 'source_id']);

        return Inertia::render('app/Recruitment/Applicants/Index', [
            'applicants' => $this->applicantService->getPaginatedApplicants($filters),
            'filters' => $filters,
            'sources' => RecruitmentSource::orderBy('sort_order')->get(['id', 'name']),
            'can' => ['create' => $request->user()->can('create', Applicant::class)],
        ]);
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    public function store(StoreApplicantRequest $request): RedirectResponse
    {
        $applicant = $this->applicantService->createApplicant($request->validated(), $request->user(), $request->boolean('confirm_not_duplicate'));

        return redirect()->route('recruitment.applicants.show', $applicant)->with('success', 'Applicant created.');
    }

    public function show(Request $request, Applicant $applicant): Response
    {
        $user = $request->user();
        $applicant->load([
            'source:id,name', 'employee:id,employee_number,emp_first_name,emp_last_name',
            'education' => fn ($q) => $q->orderByDesc('start_date'),
            'workExperience' => fn ($q) => $q->orderByDesc('from'),
            'documents' => fn ($q) => $q->with(['type:id,name', 'uploader:id,username'])->latest('uploaded_at'),
            'applications' => fn ($q) => $q->with(['vacancy:id,vacancy_number,title,status', 'currentStage:id,name,stage_type'])->latest('applied_at'),
        ]);

        $canApply = $user->can('create', Application::class) && $applicant->status === Applicant::STATUS_ACTIVE;

        return Inertia::render('app/Recruitment/Applicants/Show', [
            'applicant' => $applicant,
            'documentTypes' => ApplicantDocumentType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'sources' => RecruitmentSource::active()->get(['id', 'name']),
            'openVacancies' => $canApply
                ? Vacancy::where('status', Vacancy::STATUS_OPEN)
                    ->whereNotIn('id', $applicant->applications->pluck('vacancy_id'))
                    ->orderBy('title')->get(['id', 'vacancy_number', 'title'])
                : [],
            'can' => [
                'update' => $user->can('update', $applicant),
                'manageDocuments' => $user->can('manageDocuments', $applicant),
                'apply' => $canApply,
                'viewApplications' => $user->can('recruitment.application.view'),
            ],
        ]);
    }

    public function edit(Applicant $applicant): Response
    {
        return $this->form($applicant->load(['education', 'workExperience']));
    }

    public function update(UpdateApplicantRequest $request, Applicant $applicant): RedirectResponse
    {
        $this->applicantService->updateApplicant($applicant, $request->validated(), $request->boolean('confirm_not_duplicate'));

        return redirect()->route('recruitment.applicants.show', $applicant)->with('success', 'Applicant updated.');
    }

    protected function form(?Applicant $applicant): Response
    {
        return Inertia::render('app/Recruitment/Applicants/Form', [
            'applicant' => $applicant,
            'sources' => RecruitmentSource::active()->get(['id', 'name']),
            'employees' => $this->options->all()['employees'],
        ]);
    }
}
