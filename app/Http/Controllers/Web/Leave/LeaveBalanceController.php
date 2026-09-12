<?php

namespace App\Http\Controllers\Web\Leave;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdjustEmployeeLeaveBalanceRequest;
use App\Http\Requests\StoreEmployeeLeaveBalanceRequest;
use App\Http\Requests\UpdateEmployeeLeaveBalanceRequest;
use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveType;
use App\Services\LeaveBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveBalanceController extends Controller
{
    public function __construct(
        protected LeaveBalanceService $balanceService
    ) {}

    /**
     * Display a listing of leave balances.
     */
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'leave_type_id', 'year']);
        $perPage = $request->integer('per_page', 15);

        return Inertia::render('app/Leave/Balances/Index', [
            'balances' => $this->balanceService->getPaginatedBalances($filters, $perPage),
            'leaveTypes' => LeaveType::select('id', 'name', 'code')->where('is_active', true)->get(),
            'employees' => Employee::select('id', 'emp_first_name', 'emp_last_name', 'employee_number')->get(),
            'filters' => $filters,
        ]);
    }

    /**
     * Store a newly created leave balance.
     */
    public function store(StoreEmployeeLeaveBalanceRequest $request): RedirectResponse
    {
        $this->balanceService->createBalance($request->validated());

        return redirect()->back()->with('success', 'Leave balance initialized successfully.');
    }

    /**
     * Update the specified leave balance.
     */
    public function update(UpdateEmployeeLeaveBalanceRequest $request, EmployeeLeaveBalance $leaveBalance): RedirectResponse
    {
        $this->balanceService->updateBalance($leaveBalance, $request->validated());

        return redirect()->back()->with('success', 'Leave balance updated successfully.');
    }

    /**
     * Adjust a leave balance manually.
     */
    public function adjust(AdjustEmployeeLeaveBalanceRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->balanceService->adjustBalance(
            balanceId: $validated['leave_balance_id'],
            type: $validated['type'],
            days: (float) $validated['days'],
            reason: $validated['reason'],
            adjustedByUserId: auth()->id()
        );

        return redirect()->back()->with('success', 'Leave balance adjusted successfully.');
    }
}
