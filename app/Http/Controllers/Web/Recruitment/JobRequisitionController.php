<?php

namespace App\Http\Controllers\Web\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\ApproveJobRequisitionRequest;
use App\Http\Requests\Recruitment\CancelJobRequisitionRequest;
use App\Http\Requests\Recruitment\RejectJobRequisitionRequest;
use App\Http\Requests\Recruitment\StoreJobRequisitionRequest;
use App\Http\Requests\Recruitment\UpdateJobRequisitionRequest;
use App\Models\JobRequisition;
use App\Models\Vacancy;
use App\Services\Recruitment\RecruitmentFormOptions;
use App\Services\Recruitment\RequisitionApprovalService;
use App\Services\Recruitment\RequisitionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JobRequisitionController extends Controller
{
    public function __construct(
        protected RequisitionService $requisitionService,
        protected RequisitionApprovalService $approvalService,
        protected RecruitmentFormOptions $options
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['status', 'department_id', 'search']);

        return Inertia::render('app/Recruitment/Requisitions/Index', [
            'requisitions' => $this->requisitionService->getPaginatedRequisitions($request->user(), $filters),
            'filters' => $filters,
            'statuses' => JobRequisition::STATUSES,
            'departments' => $this->options->all()['departments'],
            'can' => ['create' => $request->user()->can('create', JobRequisition::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form($request, null);
    }

    public function store(StoreJobRequisitionRequest $request): RedirectResponse
    {
        $requisition = $this->requisitionService->createRequisition($request->validated(), $request->user());

        return redirect()->route('recruitment.requisitions.show', $requisition)->with('success', 'Requisition saved as a draft.');
    }

    public function show(Request $request, JobRequisition $requisition): Response
    {
        $user = $request->user();
        $requisition->load([
            'department:id,name', 'jobTitle:id,job_title', 'location:id,address,city', 'employmentStatus:id,name',
            'replacedEmployee:id,employee_number,emp_first_name,emp_last_name',
            'requestedBy:id,employee_number,emp_first_name,emp_last_name', 'creator:id,username',
            'approvals', 'vacancies' => fn ($q) => $q->select('id', 'job_requisition_id', 'vacancy_number', 'title', 'openings', 'status'),
        ]);

        $allocated = $requisition->allocatedOpenings();

        return Inertia::render('app/Recruitment/Requisitions/Show', [
            'requisition' => $requisition,
            'allocatedOpenings' => $allocated,
            'can' => [
                'update' => $user->can('update', $requisition),
                'submit' => $user->can('submit', $requisition),
                'approve' => $user->can('approve', $requisition),
                'cancel' => $user->can('cancel', $requisition),
                'createVacancy' => $requisition->isApproved() && $allocated < $requisition->positions && $user->can('create', Vacancy::class),
            ],
        ]);
    }

    public function edit(Request $request, JobRequisition $requisition): Response
    {
        return $this->form($request, $requisition);
    }

    public function update(UpdateJobRequisitionRequest $request, JobRequisition $requisition): RedirectResponse
    {
        $this->requisitionService->updateRequisition($requisition, $request->validated());

        return redirect()->route('recruitment.requisitions.show', $requisition)->with('success', 'Requisition updated.');
    }

    public function submit(Request $request, JobRequisition $requisition): RedirectResponse
    {
        $this->authorize('submit', $requisition);
        $this->requisitionService->submit($requisition, $request->user());

        return back()->with('success', 'Requisition submitted for approval.');
    }

    public function approve(ApproveJobRequisitionRequest $request, JobRequisition $requisition): RedirectResponse
    {
        $this->approvalService->approve($requisition, $request->user(), $request->validated('remarks'));

        return back()->with('success', 'Approval recorded.');
    }

    public function reject(RejectJobRequisitionRequest $request, JobRequisition $requisition): RedirectResponse
    {
        $this->approvalService->reject($requisition, $request->user(), $request->validated('remarks'));

        return back()->with('success', 'Requisition rejected.');
    }

    public function cancel(CancelJobRequisitionRequest $request, JobRequisition $requisition): RedirectResponse
    {
        $this->requisitionService->cancel($requisition, $request->user(), $request->validated('reason'));

        return back()->with('success', 'Requisition cancelled.');
    }

    protected function form(Request $request, ?JobRequisition $requisition): Response
    {
        $user = $request->user();

        return Inertia::render('app/Recruitment/Requisitions/Form', [
            'requisition' => $requisition,
            'options' => $this->options->all(),
            'reasons' => JobRequisition::REASONS,
            // Only organization-wide users may raise a requisition for someone else.
            'canChooseRequester' => $user->can('recruitment.requisition.view'),
            'currentEmployeeId' => $user->employee_id,
        ]);
    }
}
