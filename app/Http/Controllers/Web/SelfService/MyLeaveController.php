<?php

namespace App\Http\Controllers\Web\SelfService;

use App\Http\Controllers\Controller;
use App\Http\Requests\LeaveApplicationFormRequest;
use App\Http\Requests\StoreMyLeaveApplicationRequest;
use App\Http\Requests\UpdateLeaveApplicationFormRequest;
use App\Http\Resources\EmployeeLeaveLedgerResource;
use App\Http\Resources\EmployeeSpecialLeaveCreditResource;
use App\Models\Employee;
use App\Models\EmployeeLeaveCreditsHistory;
use App\Models\SpecialLeave;
use App\Services\LeavePdfService;
use App\Services\LeaveService;
use App\Services\MyLeavesService;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class MyLeaveController extends Controller
{
    public function index(MyLeavesService $myLeaveService)
    {

        // dd(Auth::user()->getAllPermissions());

        $leave = $myLeaveService->leaveTypes();
        $myLeaveApplications = $myLeaveService->myLeaveApplications();

        return Inertia::render('app/SelfService/MyLeaves/Index', [
            'leaveTypes' => $leave,
            'myLeaveApplications' => $myLeaveApplications
        ]);
    }

    public function apply(Request $request, MyLeavesService $myLeaveService, LeaveService $leaveService)
    {
        $leaveCredits = $request->leaveType ?
            $myLeaveService->checkLeaveCreditsBalance(['leaveType' => $request->leaveType])
            : [];

        // Get special leave credits
        // This will get the latest special leave credit for each unique document_type_number
        // Filter out expired credits (expiration_date_to must be today or in the future)
        $entitledSpecialLeaves = EmployeeLeaveCreditsHistory::with('specialLeave')
            ->where('employee_id', auth()->user()->employee->id)
            ->whereNotNull('special_leave_id')
            ->where('balance', '!=', 0)
            ->where(function ($query) {
                $query->whereNull('expiration_date_to')
                    ->orWhere('expiration_date_to', '>=', now()->startOfDay());
            })
            ->whereIn('id', function ($query) {
                $query->selectRaw('MAX(id)')
                    ->from('employee_leave_credits_histories')
                    ->where('employee_id', auth()->user()->employee->id)
                    ->whereNotNull('special_leave_id')
                    ->groupBy('document_type_number');
            })
            ->get();

        $leaveTypes = $myLeaveService->leaveTypes();

        // Compute for unpaid leaves
        $appliedLeave = $request->type ?? null;
        $appliedLeaveDates = $request->leaveDates ?? null;
        $leaveApplicationDetails = $appliedLeave && $appliedLeaveDates ? $myLeaveService->computeForUnpaidLeaves($appliedLeave, $appliedLeaveDates) : null;

        $workSchedule = Employee::where('id', auth()->user()->employee->id)
            ->first();

        // dd($leaveApplicationDetails);
        return Inertia::render('app/SelfService/MyLeaves/Create', [
            'leaveTypes' => $leaveTypes,
            'leaveCredits' => $leaveCredits,
            'specialLeaveCredits' => EmployeeSpecialLeaveCreditResource::collection($entitledSpecialLeaves),
            'leaveApplicationDetails' => $leaveApplicationDetails,
            'workSchedule' => $workSchedule?->dailyShiftSchedules()->get()->toArray() ?? [],
        ]);
    }

    public function checkLeaveCredits(LeaveApplicationFormRequest $request, MyLeavesService $myLeaveService)
    {
        $leaveCredits = $myLeaveService->checkLeaveCreditsBalance($request->validated());

        return back()->with([
            'leaveCredits' => $leaveCredits
        ]);
    }


    /* For Leave Applications */
    public function store(LeaveApplicationFormRequest $request, MyLeavesService $myLeaveService)
    {
        try {
            if ($request->validated('leaveType') === 'Others') {
                $myLeaveService->storeLeaveApplicationOthers($request->validated());
                return redirect()->route('self-service.my-leaves.index');
            } else {
                $myLeaveService->storeLeaveApplication($request->validated());
                return redirect()->route('self-service.my-leaves.index');
            }
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function cancelMyLeave(UpdateLeaveApplicationFormRequest $request, LeaveService $leaveService)
    {
        $validated = $request->validated();

        try {
            $updatedLeave = $leaveService->updateLeaveApplicationStatus($validated);

            app(\App\Services\RestoreLeaveCreditService::class)
                ->restore($updatedLeave);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function entitlements(MyLeavesService $myLeaveServices)
    {
        $data = $myLeaveServices->index();

        return Inertia::render('app/SelfService/MyLeaves/Entitlements', [
            'employeeLeaveCreditsHistory' => $data['employeeLeaveCreditsHistory'],
            'specialLeaveCreditsHistory' => $data['specialLeaveCreditsHistory'],
            'totalLeaveCredits' => $data['totalLeaveCredits'],
            'totalLeaveType' => $data['totalLeaveTypes']
        ]);
    }


    public function leaveLedger(
        Request $request,
        LeaveService $leaveService
    ) {
        $employeeId = Auth::user()->employee_id;

        $year = $request->integer('year', now()->year);

        $leaveCreditHistories = $leaveService->getLeaveCreditHistory(
            $employeeId,
            $year
        );

        return Inertia::render(
            'app/HrManagement/Leave/Entitlements/LeaveLedger',
            [
                'year' => $year,
                'leaveCreditHistories' => EmployeeLeaveLedgerResource::collection(
                    $leaveCreditHistories
                ),
                'leave_ledger_print_url' => URL::signedRoute(
                    'self-service.my-leaves.leaveLedger.print',
                    ['year' => $year]
                ),
            ]
        );
    }

    public function printLeaveLedger(
        Request $request,
        LeaveService $leaveService
    ) {
        $employeeId = Auth::user()->employee_id;

        $year = $request->integer('year', now()->year);

        $employee = $leaveService
            ->getLeaveCreditHistory($employeeId, $year)
            ->first();

        if (!$employee) {
            abort(404);
        }

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

    public function edit(string $id, MyLeavesService $myLeaveService)
    {
        $leaveApplication = $myLeaveService->editLeaveApplication($id);
        return Inertia::render('app/SelfService/MyLeaves/Edit', [
            'leaveApplication' => $leaveApplication
        ]);
    }


    public function view(Request $request, string $id, LeavePdfService $leavePdfService)
    {
        // Check if the request is properly signed
        if (!$request->hasValidSignature()) {
            abort(Response::HTTP_FORBIDDEN, 'Invalid or expired signature.');
        }

        // Continue if signature is valid
        $leaveApplication = $leavePdfService->printMyLeaveApplication($id);

        return $leaveApplication->inline('leave.pdf');
    }
}
