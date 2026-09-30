<?php

namespace App\Http\Controllers\Web\Leave;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\StoreLeaveApprovalWorkflowRequest;
use App\Http\Requests\Leave\UpdateLeaveApprovalWorkflowRequest;
use App\Models\Employee;
use App\Models\LeaveApprovalWorkflow;
use App\Models\LeaveApprovalWorkflowStep;
use App\Models\LeavePolicy;
use App\Services\Leave\LeaveApprovalWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveApprovalWorkflowController extends Controller
{
    public function __construct(protected LeaveApprovalWorkflowService $workflowService) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'is_active']);

        return Inertia::render('app/Leave/Configuration/Workflows/Index', [
            'workflows' => $this->workflowService->getPaginatedWorkflows($filters),
            'policies' => LeavePolicy::orderBy('name')->get(['id', 'name', 'is_active']),
            'employees' => Employee::whereNotIn('status', ['archived', 'terminated'])
                ->orderBy('emp_last_name')
                ->get(['id', 'emp_first_name', 'emp_last_name', 'employee_number']),
            'approverTypes' => LeaveApprovalWorkflowStep::SUPPORTED_TYPES,
            'filters' => $filters,
        ]);
    }

    public function store(StoreLeaveApprovalWorkflowRequest $request): RedirectResponse
    {
        $this->workflowService->createWorkflow($request->validated());

        return redirect()->route('leave.config.workflows.index')->with('success', 'Approval workflow created successfully.');
    }

    public function update(UpdateLeaveApprovalWorkflowRequest $request, LeaveApprovalWorkflow $leaveApprovalWorkflow): RedirectResponse
    {
        $this->workflowService->updateWorkflow($leaveApprovalWorkflow, $request->validated());

        return redirect()->route('leave.config.workflows.index')->with('success', 'Approval workflow updated successfully.');
    }

    public function destroy(LeaveApprovalWorkflow $leaveApprovalWorkflow): RedirectResponse
    {
        $this->workflowService->archiveWorkflow($leaveApprovalWorkflow);

        return redirect()->route('leave.config.workflows.index')->with('success', 'Approval workflow archived successfully.');
    }
}
