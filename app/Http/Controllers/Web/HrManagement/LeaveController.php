<?php

namespace App\Http\Controllers\Web\HrManagement;

use App\Events\LeaveStatusUpdateEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeLeaveEntitlementRequest;
use App\Http\Requests\StoreLeaveApplicationRequest;
use App\Http\Requests\StoreLeaveTypeRequest;
use App\Http\Requests\UpdateLeaveApplicationFormRequest;
use App\Http\Resources\EmployeeLeaveLedgerResource;
use App\Http\Resources\EmployeeSpecialLeaveCreditResource;
use App\Models\Employee;
use App\Models\EmployeeLeaveCreditsHistory;
use App\Models\LeaveApplication;
use App\Notifications\LeaveStatusNotification;
use App\Services\EmployeeLeaveService;
use App\Services\EmployeeService;
use App\Services\LeavePdfService;
use App\Services\LeaveService;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class LeaveController extends Controller
{
    public function index(Request $request, EmployeeLeaveService $employeeLeaveService)
    {
        /* Employee Entitlements */
        $employees = $employeeLeaveService->index();
        $employementStatus = $employeeLeaveService->employementStatus();
        $operatingUnits = $employeeLeaveService->getOperatingUnits();

        return Inertia::render('app/HrManagement/Leave/Index', [
            'employees' => $employees,
            'employementStatus' => $employementStatus,
            'operatingUnits' => $operatingUnits,
        ]);
    }

    public function leaveList(LeaveService $leaveService)
    {
        return Inertia::render('app/HrManagement/Leave/LeaveList', [
            'operatingUnits' => $leaveService->getOperatingUnits(),
            'leaveTypes' => $leaveService->getLeaveTypes(),
            'leaveApplications' => $leaveService->leaveList(),
        ]);
    }

    public function updateLeaveApplicationStatus(UpdateLeaveApplicationFormRequest $request, LeaveService $leaveService)
    {

        $validated = $request->validated();

        $leave = LeaveApplication::with('employee')->findOrFail($validated['row_id']);

        $oldStatus = $leave->status;
        $newStatus = strtolower($validated['status']);

        DB::transaction(function () use ($leaveService, $leave, $validated, $oldStatus, $newStatus) {

            $updatedLeave = $leaveService->updateLeaveApplicationStatus($validated);

            // =========================
            // RESTORE IF CANCELLED OR DISAPPROVED
            // =========================
            if (
                in_array($newStatus, ['cancelled', 'disapproved'])
            ) {
                app(\App\Services\RestoreLeaveCreditService::class)
                    ->restore($updatedLeave);
            }

            $updatedLeave->employee->notify(
                new LeaveStatusNotification($updatedLeave)
            );
        });

        return back()->with('success', 'Leave application status updated successfully');
    }

    public function addLeaveEntitlements(LeaveService $leaveService, Request $request)
    {
        /* This is the index of HRManagement/Leave/Entitlements */
        // dd($request);

        $data = $leaveService->addEmployeeLeaveEntitlements();
        $operatingUnits = $leaveService->getOperatingUnits();

        // Handle operating unit selection based on user role
        $operatingUnitId = $request->operatingUnitId;
        $user = auth()->user();
        $isPrivilegedUser = in_array($user->roles[0]->name ?? '', ['superadmin', 'hr_director']);

        // If user is not privileged, use their operating unit
        if (!$isPrivilegedUser && $user->employee && $user->employee->operating_unit_id) {
            $operatingUnitId = $user->employee->operating_unit_id;
        }

        $countEmployeePerOperatingUnit = $operatingUnitId ?
            $leaveService->countEmployeePerOperatingUnit($operatingUnitId) : 0;
        $operatingUnitDepartments = $operatingUnitId ? $leaveService->getOperatingUnitDepartments($operatingUnitId) : [];

        // Handle multiple departments
        $departmentIds = $request->departmentIds;
        if ($departmentIds && is_array($departmentIds) && count($departmentIds) > 0) {
            $employeesPerOperatingUnitAndDepartment = $leaveService->getEmployeesPerOperatingUnitAndDepartment($operatingUnitId, $departmentIds);
        } else {
            $employeesPerOperatingUnitAndDepartment = [];
        }

        // Handle special leaves request
        $specialLeaves = [];
        if ($request->getSpecialLeaves) {
            $specialLeaves = $leaveService->getSpecialLeaves();
        }

        return Inertia::render('app/HrManagement/Leave/Entitlements/Index', [
            'leaveTypes' => $data['leaveTypes'],
            'employees' => $data['employees'],
            'operatingUnits' => $operatingUnits,
            'countEmployeePerOperatingUnit' => $countEmployeePerOperatingUnit,
            'operatingUnitDepartments' => $operatingUnitDepartments,
            'employeesPerOperatingUnitAndDepartment' => $employeesPerOperatingUnitAndDepartment,
            'specialLeaves' => $specialLeaves
        ]);
    }

    public function viewLeaveLedger(
        Request $request,
        string $employee_id,
        LeaveService $leaveService
    ) {
        $year = $request->integer('year', now()->year);

        $leaveCreditHistories = $leaveService->getLeaveCreditHistory(
            $employee_id,
            $year
        );

        return Inertia::render(
            'app/HrManagement/Leave/Entitlements/LeaveLedger',
            [
                'year' => $year,
                'leaveCreditHistories' => EmployeeLeaveLedgerResource::collection(
                    $leaveCreditHistories
                ),
            ]
        );
    }

    public function printLeaveLedger(
        Request $request,
        Employee $employee,
        LeaveService $leaveService
    ) {
        $year = $request->integer('year', now()->year);

        $employee = $leaveService
            ->getLeaveCreditHistory($employee->id, $year)
            ->first();

        // Sort oldest to newest for the PDF
        $employee->setRelation(
            'leaveCreditsHistory',
            $employee->leaveCreditsHistory
                ->sortBy('created_at')
                ->values()
        );

        $isTeachingWithoutDesignation =
            $employee->employee_type === 'Teaching' &&
            $employee->employeeDesignations->isEmpty();

        $view = $isTeachingWithoutDesignation
            ? 'templates.leave.leave-ledger-tl'
            : 'templates.leave.leave-ledger-vsl';

        return SnappyPdf::loadView($view, [
            'employee' => $employee,
            'year' => $year,
        ])
            ->setPaper('legal')
            ->setOrientation('landscape')
            ->setOption('margin-top', '8mm')
            ->setOption('margin-right', '8mm')
            ->setOption('margin-bottom', '8mm')
            ->setOption('margin-left', '8mm')
            ->inline("leave-ledger-{$year}.pdf");
    }

    protected function isFacultyWithoutDesignation($employee)
    {
        return $employee->personalInformation->employment_status === 'Faculty' &&
            empty($employee->personalInformation->designation);
    }

    public function storeEmployeeLeaveEntitlements(StoreEmployeeLeaveEntitlementRequest $request, LeaveService $leaveService)
    {
        if ($request->allOrNot === 'individual') {
            try {
                $leaveService->storeSingleEmployeeLeaveEntitlements($request->validated());
                return redirect()->back()->with('success', 'Leave entitlements added successfully');
            } catch (\Exception $e) {
                return redirect()->back()->withErrors(['error' => $e->getMessage()]);
            }
        }

        if ($request->allOrNot === 'multiple') {

            try {
                $leaveService->storeMultipleEmployeeLeaveEntitlements($request->validated());
                // return redirect()->back()->with('success', 'Leave entitlements added successfully');
                return redirect()->route('hrmanagement.leave.index')->with('success', 'Leave entitlements added successfully');
            } catch (\Exception $e) {
                return redirect()->back()->withErrors(['error' => $e->getMessage()]);
            }
        }
    }

    public function viewEmployeeLeaveEntitlements(string $id, LeaveService $employeeEntitlements)
    {
        $employeeEntitlements = $employeeEntitlements->employeeLeaveEntitlements($id);

        return Inertia::render('app/HrManagement/Leave/EmployeeLeave', [
            'employee' => $employeeEntitlements['employee'],
            'employeeEntitlements' => $employeeEntitlements['leaveCreditsHistory'],
            'specialEmployeeEntitlements' => $employeeEntitlements['specialLeaveCreditsHistory'],
            'leave_ledger_link' => $employeeEntitlements['leave_ledger_link']
        ]);
    }

    public function deductLeave(Request $request, LeaveService $leaveService)
    {

        $validated = $request->validate([
            'employee_id' => 'required|integer',
            'entitlement_id' => 'required|integer',
            'value' => 'required|numeric',
            'credit_origin' => 'required|string',
            'remarks' => 'nullable|string',
        ]);

        try {
            $leaveService->deductLeave($validated);
            return redirect()->back()->with('success', 'Leave deducted successfully');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to deduct leave: ' . $e->getMessage()]);
        }
    }

    public function deductSpecialLeave(Request $request, LeaveService $leaveService)
    {

        $validated = $request->validate([
            'employee_id' => 'required|integer',
            'special_entitlement_id' => 'required|integer',
            'value' => 'required|numeric',
            'credit_origin' => 'required|string',
            'remarks' => 'nullable|string',
        ]);

        try {
            $leaveService->deductSpecialLeave($validated);
            return redirect()->back();
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to deduct special leave: ' . $e->getMessage()]);
        }
    }

    public function deleteEmployeeLeaveEntitlements(string $id, LeaveService $leaveService)
    {

        try {
            $leaveService->deleteEmployeeLeaveEntitlements($id);
            return redirect()->back()->with('success', 'Leave entitlements deleted successfully');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete leave entitlements: ' . $e->getMessage()]);
        }
    }

    public function leaveTypeIndex(LeaveService $leaveService)
    {
        $leaveTypes = $leaveService->leaveTypeIndex();
        // dd($leaveTypes['leaveTypes']);
        return Inertia::render('app/HrManagement/Leave/LeaveType/Index', [
            'leaveTypes' => $leaveTypes['leaveTypes'],
            'totalCountOfLeaveTypes' => $leaveTypes['totalCountOfLeaveTypes']
        ]);
    }

    public function leaveTypeCreate()
    {

        return Inertia::render('app/HrManagement/Leave/LeaveType/Create');
    }

    public function leaveTypeStore(StoreLeaveTypeRequest $request, LeaveService $leaveService)
    {
        try {
            $leaveService->storeLeaveType($request->validated());
            return redirect()->route('hrmanagement.leave.leaveType.index')->with('success', 'Leave type created successfully');
        } catch (\Exception $e) {
            return redirect()->route('hrmanagement.leave.leaveType.index')
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function leaveTypeDelete($id, LeaveService $leaveService)
    {
        try {
            $leaveService->deleteLeaveType((int) $id);
            return redirect()->route('hrmanagement.leave.leaveType.index')->with('success', 'Leave type deleted successfully');
        } catch (\Exception $e) {
            return redirect()->route('hrmanagement.leave.leaveType.index')
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function leaveTypeDeleteSelected(Request $request, LeaveService $leaveService)
    {
        $ids = $request->input('ids', []);

        try {
            $leaveService->deleteSelectedLeaveTypes($ids);
            return redirect()->route('hrmanagement.leave.leaveType.index')->with('success', 'Leave types deleted successfully');
        } catch (\Exception $e) {
            return redirect()->route('hrmanagement.leave.leaveType.index')
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function viewLeaveApplication(Request $request, string $id, LeaveService $leaveService)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Invalid or expired link');
        }
        return Inertia::render('app/HrManagement/Leave/View', [
            'leaveApplication' => $leaveService->viewLeaveApplication(id: $id)
        ]);
    }

    public function printLeaveApplication(Request $request, string $id, LeavePdfService $leavePdfService)
    {
        // Check if the request is properly signed
        if (!$request->hasValidSignature()) {
            abort(Response::HTTP_FORBIDDEN, 'Invalid or expired signature.');
        }

        // Continue if signature is valid
        $leaveApplication = $leavePdfService->printMyLeaveApplication($id);

        return $leaveApplication->inline('leave.pdf');
    }

    public function assignLeave(Request $request, LeaveService $leaveService)
    {

        $employees = $leaveService->fetchEmployees();

        $leaveTypes = $leaveService->getLeaveTypes();

        // Normalize employee_id: handle both scalar IDs and array/OBJECT with ['id' => ...]
        $employeeId = $request->employee_id;

        if (is_array($employeeId)) {
            $employeeId = $employeeId['id'] ?? null;
        }

        $leaveCredits = ($request->leaveType && $employeeId)
            ? $leaveService->checkLeaveCreditsBalance(
                ['leaveType' => $request->leaveType],
                (int) $employeeId
            )
            : [];

        $entitledSpecialLeaves = EmployeeLeaveCreditsHistory::with('specialLeave')
            ->where('employee_id', $employeeId)
            ->whereNotNull('special_leave_id')
            ->where('balance', '!=', 0)
            ->where(function ($query) {
                $query->whereNull('expiration_date_to')
                    ->orWhere('expiration_date_to', '>=', now()->startOfDay());
            })
            ->whereIn('id', function ($query) use ($employeeId) {
                $query->selectRaw('MAX(id)')
                    ->from('employee_leave_credits_histories')
                    ->where('employee_id', $employeeId)
                    ->whereNotNull('special_leave_id')
                    ->groupBy('document_type_number');
            })
            ->get();

        // dd()



        return Inertia::render('app/HrManagement/Leave/AssignLeave/AssignLeave', [
            'employees' => $employees,
            'leaveTypes' => $leaveTypes,
            'leaveCredits' => $leaveCredits,
            'specialLeaveCredits' => EmployeeSpecialLeaveCreditResource::collection($entitledSpecialLeaves)
        ]);
    }

    public function storeAssignLeave(StoreLeaveApplicationRequest $request, LeaveService $leaveService)
    {

        try {
            if ($request->validated('leaveType') === 'Others') {
                $leaveService->storeLeaveApplicationOthers($request->validated());
            } else {
                $leaveService->storeLeaveApplication($request->validated());
                return redirect()->route('hrmanagement.leave.leaveList');
            }
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }


}
