<?php

namespace App\Http\Controllers\Web\Leave;

use App\Http\Controllers\Controller;
use App\Models\LeaveApplication;
use App\Services\Leave\LeaveApprovalService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LeaveApprovalController extends Controller
{
    public function __construct(
        protected LeaveApprovalService $approvalService
    ) {}

    /**
     * Display pending leave approval requests.
     */
    public function index(Request $request)
    {
        $employeeId = $request->user()->employee->id;
        $approvals = $this->approvalService->getPendingApprovals($employeeId, $request->all());

        return Inertia::render('app/Leave/Approvals/Index', [
            'approvals' => $approvals,
            'filters' => $request->only(['leave_type_id']),
        ]);
    }

    /**
     * Approve leave application.
     */
    public function approve(Request $request, LeaveApplication $leaveApplication)
    {
        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $employeeId = $request->user()->employee->id;
        $this->approvalService->approve($leaveApplication, $employeeId, $validated['remarks'] ?? null);

        return redirect()->back()->with('success', 'Leave application approved successfully.');
    }

    /**
     * Reject leave application.
     */
    public function reject(Request $request, LeaveApplication $leaveApplication)
    {
        $validated = $request->validate([
            'remarks' => ['required', 'string', 'max:500'],
        ]);

        $employeeId = $request->user()->employee->id;
        $this->approvalService->reject($leaveApplication, $employeeId, $validated['remarks']);

        return redirect()->back()->with('success', 'Leave application rejected.');
    }

    /**
     * Return leave application to employee for correction.
     */
    public function return(Request $request, LeaveApplication $leaveApplication)
    {
        $validated = $request->validate([
            'remarks' => ['required', 'string', 'max:500'],
        ]);

        $employeeId = $request->user()->employee->id;
        $this->approvalService->return($leaveApplication, $employeeId, $validated['remarks']);

        return redirect()->back()->with('success', 'Leave application returned to employee.');
    }
}
