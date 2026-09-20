<?php

namespace App\Http\Controllers\Web\Leave;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\StoreLeaveApplicationRequest;
use App\Http\Requests\Leave\UpdateLeaveApplicationRequest;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Services\Leave\LeaveApplicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveApplicationController extends Controller
{
    public function __construct(
        protected LeaveApplicationService $service
    ) {}

    public function index(Request $request): Response
    {
        $employeeId = $request->user()->employee?->id;
        $start = $request->input('start_date', now()->startOfMonth()->toDateString());
        $end = $request->input('end_date', now()->addMonth()->endOfMonth()->toDateString());

        return Inertia::render('app/Leave/Applications/Index', [
            'applications' => $this->service->getPaginatedApplications($employeeId, $request->only(['status', 'leave_type_id'])),
            'history' => $this->service->getApplicationHistory($employeeId, $request->only(['status', 'leave_type_id'])),
            'balances' => $this->service->getEmployeeBalances($employeeId),
            'teamEvents' => $this->service->getTeamLeaveCalendarEvents($employeeId, $start, $end),
            'leaveTypes' => LeaveType::select('id', 'name', 'code')->get(),
            'filters' => $request->only(['status', 'leave_type_id']),
        ]);
    }

    public function store(StoreLeaveApplicationRequest $request): RedirectResponse
    {
        $employeeId = $request->user()->employee?->id;

        $data = $request->only(['leave_type_id', 'reason']);
        $dates = $request->input('dates', []);
        $files = $request->file('attachments', []);

        $this->service->createApplication($employeeId, $data, $dates, $files);

        return back()->with('success', 'Leave application submitted successfully.');
    }

    public function update(UpdateLeaveApplicationRequest $request, LeaveApplication $leaveApplication): RedirectResponse
    {
        $this->authorize('update', $leaveApplication);

        $data = $request->only(['leave_type_id', 'reason']);
        $dates = $request->input('dates', []);
        $files = $request->file('attachments', []);

        $this->service->updateApplication($leaveApplication, $data, $dates, $files);

        return back()->with('success', 'Leave application updated successfully.');
    }

    public function cancel(LeaveApplication $leaveApplication): RedirectResponse
    {
        $this->authorize('cancel', $leaveApplication);

        $this->service->cancelApplication($leaveApplication);

        return back()->with('success', 'Leave application cancelled successfully.');
    }
}
