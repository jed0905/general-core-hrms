<?php

namespace App\Http\Controllers\Web\Leave;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\ApproveLeaveApplicationRequest;
use App\Http\Requests\Leave\RejectLeaveApplicationRequest;
use App\Http\Requests\Leave\ReturnLeaveApplicationRequest;
use App\Models\LeaveApplication;
use App\Services\Leave\LeaveApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class LeaveApprovalController extends Controller
{
    public function __construct(
        protected LeaveApprovalService $approvalService
    ) {}

    /**
     * Display the leave applications waiting on the current user's approval.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(['leave_type_id']);

        $approvals = $user->employee_id === null
            ? new LengthAwarePaginator([], 0, 15)
            : $this->approvalService->getPendingApprovals($user->employee_id, $filters)
                ->through(fn (LeaveApplication $application) => array_merge($application->toArray(), [
                    'can' => [
                        'approve' => $user->can('approve', $application),
                        'reject' => $user->can('reject', $application),
                        'return' => $user->can('return', $application),
                    ],
                ]));

        return Inertia::render('app/Leave/Approvals/Index', [
            'approvals' => $approvals,
            'filters' => $filters,
        ]);
    }

    public function approve(ApproveLeaveApplicationRequest $request, LeaveApplication $leaveApplication): RedirectResponse
    {
        $this->approvalService->approve($leaveApplication, $request->user()->employee_id, $request->validated('remarks'));

        return redirect()->back()->with('success', 'Leave application approved successfully.');
    }

    public function reject(RejectLeaveApplicationRequest $request, LeaveApplication $leaveApplication): RedirectResponse
    {
        $this->approvalService->reject($leaveApplication, $request->user()->employee_id, $request->validated('remarks'));

        return redirect()->back()->with('success', 'Leave application rejected.');
    }

    /**
     * Return leave application to employee for correction.
     */
    public function return(ReturnLeaveApplicationRequest $request, LeaveApplication $leaveApplication): RedirectResponse
    {
        $this->approvalService->return($leaveApplication, $request->user()->employee_id, $request->validated('remarks'));

        return redirect()->back()->with('success', 'Leave application returned to employee.');
    }
}
