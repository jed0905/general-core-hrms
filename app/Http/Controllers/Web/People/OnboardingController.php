<?php

namespace App\Http\Controllers\Web\People;

use App\Http\Controllers\Controller;
use App\Http\Requests\People\OnboardingNoteRequest;
use App\Http\Requests\People\OnboardingReasonRequest;
use App\Http\Requests\People\OnboardingTaskRequest;
use App\Http\Requests\People\StartOnboardingRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeDocumentType;
use App\Models\Onboarding;
use App\Models\OnboardingTemplate;
use App\Services\Onboarding\OnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * HR onboarding management (People → Onboarding).
 */
class OnboardingController extends Controller
{
    public function __construct(protected OnboardingService $onboardings) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status', 'template_id', 'department_id', 'supervisor_id', 'start_from', 'start_to', 'overdue']);

        return Inertia::render('app/People/Onboarding/Index', [
            'onboardings' => $this->onboardings->getPaginatedOnboardings($filters),
            'filters' => $filters,
            'stats' => $this->onboardings->dashboard(),
            'statuses' => Onboarding::STATUSES,
            'templates' => OnboardingTemplate::orderBy('name')->get(['id', 'name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'supervisors' => Employee::whereIn('id', Employee::whereNotNull('supervisor_id')->select('supervisor_id'))
                ->orderBy('emp_last_name')->get(['id', 'emp_first_name', 'emp_last_name']),
            'can' => ['create' => $request->user()->can('create', Onboarding::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('app/People/Onboarding/Create', [
            'employees' => Employee::whereNotIn('status', ['archived', 'terminated'])
                ->orderBy('emp_last_name')->orderBy('emp_first_name')
                ->get(['id', 'employee_number', 'emp_first_name', 'emp_last_name', 'employment_status_id', 'joined_date', 'status']),
            'templates' => OnboardingTemplate::where('is_active', true)->withCount(['tasks' => fn ($q) => $q->where('is_active', true)])
                ->orderBy('name')->get(['id', 'name', 'description', 'employment_status_id']),
            'activeEmployeeIds' => Onboarding::whereIn('status', Onboarding::ACTIVE)->pluck('employee_id'),
            'completedEmployeeIds' => Onboarding::where('status', Onboarding::STATUS_COMPLETED)->distinct()->pluck('employee_id'),
            'prefill' => $request->only(['employee_id', 'application_conversion_id']),
        ]);
    }

    public function store(StartOnboardingRequest $request): RedirectResponse
    {
        $onboarding = $this->onboardings->start(Employee::findOrFail($request->integer('employee_id')), $request->validated(), $request->user());

        return redirect()->route('people.onboarding.show', $onboarding)->with('success', 'Onboarding started.');
    }

    public function show(Request $request, Onboarding $onboarding): Response
    {
        $user = $request->user();
        $onboarding->load([
            'employee:id,employee_number,emp_first_name,emp_last_name,department_id,supervisor_id,job_title_id,status,joined_date',
            'employee.department:id,name', 'employee.supervisor:id,emp_first_name,emp_last_name', 'employee.jobTitle:id,job_title',
            'tasks' => fn ($q) => $q->with([
                'assignee:id,emp_first_name,emp_last_name', 'completer:id,username', 'verifier:id,username',
                'requiredDocumentType:id,name', 'document:id,original_name',
            ]),
            'events' => fn ($q) => $q->with(['actor:id,username', 'task:id,title']),
            'notes', 'creator:id,username', 'completer:id,username', 'canceller:id,username',
        ]);
        $onboarding->tasks->each(fn ($task) => $task->setAttribute('can', [
            'act' => $user->can('act', $task),
            'verify' => $user->can('verify', $task),
            'skip' => $user->can('skip', $task),
        ]));
        $progress = $this->onboardings->withProgress(Onboarding::whereKey($onboarding->id))->first();
        $canSeeDocuments = $user->can('employee.documents.view');

        return Inertia::render('app/People/Onboarding/Show', [
            'onboarding' => $onboarding,
            'progress' => $progress->only(['tasks_total', 'tasks_done', 'required_total', 'required_done', 'overdue_count']),
            'completionProblem' => $onboarding->isActive() ? $this->onboardings->completionProblem($onboarding) : null,
            'employeeDocuments' => $canSeeDocuments
                ? EmployeeDocument::where('employee_id', $onboarding->employee_id)->get(['id', 'employee_document_type_id', 'original_name'])
                : [],
            'documentTypes' => EmployeeDocumentType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'employees' => $user->can('update', $onboarding)
                ? Employee::whereNotIn('status', ['archived', 'terminated'])->orderBy('emp_last_name')->get(['id', 'employee_number', 'emp_first_name', 'emp_last_name'])
                : [],
            'can' => [
                'update' => $user->can('update', $onboarding),
                'complete' => $user->can('complete', $onboarding),
                'cancel' => $user->can('cancel', $onboarding),
                'viewEmployee' => $user->can('view', $onboarding->employee),
                'downloadDocuments' => $canSeeDocuments,
            ],
        ]);
    }

    public function complete(Request $request, Onboarding $onboarding): RedirectResponse
    {
        $this->onboardings->complete($onboarding, $request->user(), $request->string('remarks')->toString() ?: null);

        return back()->with('success', 'Onboarding completed.');
    }

    public function cancel(OnboardingReasonRequest $request, Onboarding $onboarding): RedirectResponse
    {
        $this->onboardings->cancel($onboarding, $request->validated('reason'), $request->user());

        return back()->with('success', 'Onboarding cancelled.');
    }

    public function storeTask(OnboardingTaskRequest $request, Onboarding $onboarding): RedirectResponse
    {
        $this->onboardings->addTask($onboarding, $request->validated(), $request->user());

        return back()->with('success', 'Task added.');
    }

    public function storeNote(OnboardingNoteRequest $request, Onboarding $onboarding): RedirectResponse
    {
        $this->onboardings->addNote($onboarding, $request->validated('body'), $request->user());

        return back()->with('success', 'Note added.');
    }
}
