<?php

namespace App\Http\Controllers\Web\Leave;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\StoreLeaveApplicationCommentRequest;
use App\Http\Requests\Leave\StoreLeaveApplicationRequest;
use App\Http\Requests\Leave\UpdateLeaveApplicationRequest;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveApplicationAttachment;
use App\Models\LeaveType;
use App\Services\Leave\LeaveApplicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveApplicationController extends Controller
{
    public function __construct(
        protected LeaveApplicationService $service
    ) {}

    public function index(Request $request): Response
    {
        $employeeId = $request->user()->employee_id;
        $filters = $request->only(['status', 'leave_type_id']);
        $start = $request->input('start_date', now()->startOfMonth()->toDateString());
        $end = $request->input('end_date', now()->addMonth()->endOfMonth()->toDateString());

        // Self-service needs an employee record; accounts without one see an empty page.
        if ($employeeId === null) {
            return Inertia::render('app/Leave/Applications/Index', [
                'applications' => new LengthAwarePaginator([], 0, 15),
                'history' => new LengthAwarePaginator([], 0, 15),
                'balances' => [],
                'teamEvents' => [],
                'leaveTypes' => LeaveType::select('id', 'name', 'code')->get(),
                'filters' => $filters,
                'hasEmployeeRecord' => false,
            ]);
        }

        $user = $request->user();
        $withAbilities = fn (LeaveApplication $application) => array_merge($application->toArray(), [
            'can' => [
                'view' => $user->can('view', $application),
                'update' => $user->can('update', $application),
                'cancel' => $user->can('cancel', $application),
            ],
        ]);

        return Inertia::render('app/Leave/Applications/Index', [
            'applications' => $this->service->getPaginatedApplications($employeeId, $filters)->through($withAbilities),
            'history' => $this->service->getApplicationHistory($employeeId, $filters)->through($withAbilities),
            'balances' => $this->service->getEmployeeBalances($employeeId),
            'teamEvents' => $this->service->getTeamLeaveCalendarEvents($employeeId, $start, $end),
            'leaveTypes' => LeaveType::select('id', 'name', 'code')->get(),
            'filters' => $filters,
            'hasEmployeeRecord' => true,
        ]);
    }

    public function show(Request $request, LeaveApplication $leaveApplication): Response
    {
        $this->authorize('view', $leaveApplication);

        $user = $request->user();

        return Inertia::render('app/Leave/Applications/Show', [
            'application' => $this->service->loadDetails($leaveApplication),
            'can' => [
                'update' => $user->can('update', $leaveApplication),
                'cancel' => $user->can('cancel', $leaveApplication),
                'comment' => $user->can('comment', $leaveApplication),
                'approve' => $user->can('approve', $leaveApplication),
                'reject' => $user->can('reject', $leaveApplication),
                'return' => $user->can('return', $leaveApplication),
            ],
        ]);
    }

    /**
     * Entry point for "Apply Leave": the applications page opens its filing form.
     */
    public function create(): RedirectResponse
    {
        return redirect()->route('leave.applications.index', ['apply' => 1]);
    }

    /**
     * The user's own leave balances. There is no employee parameter: the
     * employee is always users.employee_id.
     */
    public function myBalances(Request $request): Response
    {
        $employee = $request->user()->employee_id ? Employee::find($request->user()->employee_id) : null;

        if ($employee) {
            $this->authorize('viewLeaveBalance', $employee);
        }

        return Inertia::render('app/Leave/MyBalances/Index', [
            'hasEmployeeRecord' => $employee !== null,
            'balances' => $employee ? $this->service->getBalanceSummary($employee->id) : [],
        ]);
    }

    /**
     * The user's own decided applications (approved, rejected, cancelled).
     */
    public function history(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(['status', 'leave_type_id']);

        return Inertia::render('app/Leave/MyHistory/Index', [
            'hasEmployeeRecord' => $user->employee_id !== null,
            'history' => $user->employee_id === null
                ? new LengthAwarePaginator([], 0, 15)
                : $this->service->getApplicationHistory($user->employee_id, $filters)
                    ->through(fn (LeaveApplication $application) => array_merge($application->toArray(), [
                        'can' => ['view' => $user->can('view', $application)],
                    ])),
            'leaveTypes' => LeaveType::select('id', 'name', 'code')->get(),
            'filters' => $filters,
        ]);
    }

    public function store(StoreLeaveApplicationRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->service->createApplication(
            $request->user()->employee_id,
            $validated,
            $validated['dates'],
            $request->file('attachments', [])
        );

        return back()->with('success', 'Leave application submitted successfully.');
    }

    public function update(UpdateLeaveApplicationRequest $request, LeaveApplication $leaveApplication): RedirectResponse
    {
        $validated = $request->validated();

        $this->service->updateApplication(
            $leaveApplication,
            $validated,
            $validated['dates'],
            $request->file('attachments', []),
            $request->user()->employee_id
        );

        return back()->with('success', 'Leave application updated successfully.');
    }

    public function cancel(Request $request, LeaveApplication $leaveApplication): RedirectResponse
    {
        $this->authorize('cancel', $leaveApplication);

        // Re-authorized inside the service against the locked, current row.
        $this->service->cancelApplication($leaveApplication, $request->user());

        return back()->with('success', 'Leave application cancelled successfully.');
    }

    public function storeComment(StoreLeaveApplicationCommentRequest $request, LeaveApplication $leaveApplication): RedirectResponse
    {
        $this->service->addComment($leaveApplication, $request->user()->employee_id, $request->validated('comment'));

        return back()->with('success', 'Comment added.');
    }

    /**
     * Attachments live on the private disk; this is the only way to read them.
     */
    public function downloadAttachment(LeaveApplication $leaveApplication, LeaveApplicationAttachment $attachment): StreamedResponse
    {
        $this->authorize('viewAttachments', $leaveApplication);

        foreach ([LeaveApplicationAttachment::DISK, LeaveApplicationAttachment::LEGACY_DISK] as $disk) {
            if (Storage::disk($disk)->exists($attachment->file_path)) {
                return Storage::disk($disk)->download($attachment->file_path, $attachment->file_name);
            }
        }

        abort(404);
    }
}
