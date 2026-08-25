<?php

namespace App\Services;

use App\Http\Filters\LeaveApplicationFilter;
use App\Http\Resources\EmployeeLeaveCreditsHistoryResource;
use App\Http\Resources\EmployeeLeaveLedgerResource;
use App\Http\Resources\EmployeePerOperatingUnitAndDepartmentResource;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\LeaveApplicationListResource;
use App\Http\Resources\LeaveApplicationResource;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeAttendanceLogEvents;
use App\Models\EmployeeLeaveCreditsHistory;
use App\Models\EmployeeSubordinate;
use App\Models\Leave;
use App\Models\LeaveApplication;
use App\Models\LeaveStatus;
use App\Models\OperatingUnit;
use App\Models\PersonalInformation;
use App\Models\SpecialLeave;
use App\Notifications\LeaveApplicationNotification;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasPermissions;

use function PHPUnit\Framework\throwException;

class LeaveService
{
    public function index()
    {
    }

    public function leaveTypeIndex()
    {
        $leaveTypes = Leave::get()
            ->map(function ($leaveType) {
                $leaveType->edit_link = URL::signedRoute('hrmanagement.leave.leaveType.edit', ['id' => $leaveType->id]);

                return $leaveType;
            });
        $totalCountOfLeaveTypes = $leaveTypes->count();

        return [
            'leaveTypes' => $leaveTypes,
            'totalCountOfLeaveTypes' => $totalCountOfLeaveTypes,
        ];
    }




    public function storeLeaveType(array $validated)
    {
        $leaveType = Leave::create($validated);

        return $leaveType;
    }

    public function deleteLeaveType(int $id)
    {
        $leaveType = Leave::find($id);

        if (!$leaveType) {
            throw new \Exception('Leave type not found.');
        }

        $leaveType->delete();

        return $leaveType;
    }

    public function deleteSelectedLeaveTypes(array $ids)
    {
        // Add validation to ensure IDs exist
        $leaveTypes = Leave::whereIn('id', $ids)->get();

        if ($leaveTypes->isEmpty()) {
            throw new \Exception('No leave types found with the provided IDs.');
        }

        $leaveTypes->each(function ($leaveType) {
            if ($leaveType) {
                $leaveType->delete();
            }
        });

        return $leaveTypes;
    }

    public function employeeLeaveEntitlements(string $id)
    {

        $employee = Employee::with(
            'personalInformation',
            'department',
            'operatingUnit',
            'position.government_position'
        )
            ->where('id', $id)->first();

        $leaveCreditsHistory = EmployeeLeaveCreditsHistory::with('leaveType')
            ->join('leaves', 'leaves.id', '=', 'employee_leave_credits_histories.leave_id')
            ->where('leaves.name', '!=', 'Others')
            ->where('employee_leave_credits_histories.employee_id', $id)
            ->whereIn('employee_leave_credits_histories.id', function ($query) use ($id) {
                $query->selectRaw('MAX(id)')
                    ->from('employee_leave_credits_histories as elch2')
                    ->whereColumn('elch2.leave_id', 'employee_leave_credits_histories.leave_id')
                    ->where('elch2.employee_id', $id);
            })
            ->select(
                'employee_leave_credits_histories.leave_id',
                'employee_leave_credits_histories.balance as total_balance',
                'employee_leave_credits_histories.total_earned'
            )
            ->get();

        $specialLeaveCreditsHistory = EmployeeLeaveCreditsHistoryResource::collection(
            EmployeeLeaveCreditsHistory::with('specialLeave')
                ->where('employee_id', $id)
                ->whereNotNull('special_leave_id')
                // ->where('balance', '!=', 0)
                ->whereIn('id', function ($query) use ($id) {
                    $query->selectRaw('MAX(id)')
                        ->from('employee_leave_credits_histories')
                        ->where('employee_id', $id)
                        ->whereNotNull('special_leave_id')
                        ->groupBy('document_type_number');
                })
                ->get()
        );

        return [
            'employee' => $employee,
            'leaveCreditsHistory' => $leaveCreditsHistory,
            'specialLeaveCreditsHistory' => $specialLeaveCreditsHistory,

            // 'leave_ledger_link' => URL::temporarySignedRoute(
            //     'hrmanagement.leave.viewEmployeeLeaveLedger',
            //     now()->addMinutes(30),
            //     ['id' => $id]
            // ),

            'leave_ledger_link' => route(
                'hrmanagement.leave.viewEmployeeLeaveLedger',
                [
                    'id' => $id,
                    'year' => $request['year'] ?? now()->year, // Default to current year if not provided
                ]
            ),
        ];
    }

    public function getLeaveCreditHistory(string $id, ?int $year = null)
    {
        $year ??= now()->year;

        return Employee::with([
            'personalInformation',
            'department',
            'leaveCreditsHistory' => function ($query) use ($year) {
                $query
                    ->whereYear('created_at', $year)
                    ->where(function ($q) {
                        $q->where('credit_addition', '!=', 0)
                            ->orWhere('credit_deduction', '!=', 0);
                    })
                    ->where(function ($q) {
                        $q->whereNull('special_leave_id')
                            ->orWhereHas('specialLeave', function ($q) {
                                $q->where('shortcut', '!=', 'COC');
                            });
                    })
                    ->orderBy('created_at');
            },
            'leaveCreditsHistory.leaveType',
            'leaveCreditsHistory.specialLeave',
            'employeeDesignations',
        ])
            ->whereKey($id)
            ->get();
    }

    public function deductLeave(array $validated)
    {
        // Expecting: ['employee_id' => int, 'entitlement_id' => int (leave_id), 'value' => numeric]
        $employeeId = $validated['employee_id'];
        $leaveId = $validated['entitlement_id'];
        $amountToDeduct = (float) $validated['value'];

        if ($amountToDeduct <= 0) {
            throw new \InvalidArgumentException('Deduction amount must be greater than zero.');
        }

        // Normal Leave History
        $employeeLeaveCreditsHistory = EmployeeLeaveCreditsHistory::where('employee_id', $employeeId)
            ->where('leave_id', $leaveId)
            ->latest()->first();

        $employeeLeaveCreditHistoryDeducted = EmployeeLeaveCreditsHistory::create([
            'employee_id' => $employeeId,
            'leave_id' => $leaveId,
            'total_earned' => $employeeLeaveCreditsHistory->balance,
            'credit_addition' => 0,
            'credit_deduction' => $amountToDeduct,
            'balance' => $employeeLeaveCreditsHistory->balance - $amountToDeduct,
            'credit_origin' => $validated['credit_origin'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
        ]);

        return $employeeLeaveCreditHistoryDeducted;
    }

    public function deductSpecialLeave($validated)
    {

        $employeeId = $validated['employee_id'];
        $specialLeaveId = $validated['special_entitlement_id'];
        $amountToDeduct = (float) $validated['value'];


        // if ($amountToDeduct <= 0) {
        //     throw new \InvalidArgumentException('Deduction amount must be greater than zero.');
        // }
        $employeeLeaveCreditsHistory = EmployeeLeaveCreditsHistory::where('id', $specialLeaveId)
            ->latest()->first();

        $employeeLeaveCreditHistoryDeducted = EmployeeLeaveCreditsHistory::create([
            'employee_id' => $employeeId,
            'leave_id' => $employeeLeaveCreditsHistory->leave_id,
            'special_leave_id' => $employeeLeaveCreditsHistory->special_leave_id,
            'total_earned' => $employeeLeaveCreditsHistory->balance,
            'credit_addition' => 0,
            'credit_deduction' => $amountToDeduct,
            'document_type_number' => $employeeLeaveCreditsHistory->document_type_number,
            'expiration_date_from' => $employeeLeaveCreditsHistory->expiration_date_from,
            'expiration_date_to' => $employeeLeaveCreditsHistory->expiration_date_to,
            'balance' => $employeeLeaveCreditsHistory->balance - $amountToDeduct,
            'credit_origin' => $validated['credit_origin'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
        ]);
        return $employeeLeaveCreditHistoryDeducted;
    }

    public function deleteEmployeeLeaveEntitlements(string $id)
    {
        $employeeLeaveCreditsHistory = EmployeeLeaveCreditsHistory::where('id', $id)->first();
        $employeeLeaveCreditsHistory->delete();
        return $employeeLeaveCreditsHistory;
    }

    public function addEmployeeLeaveEntitlements()
    {

        $leaveTypes = Leave::whereIn('name', [
            'Vacation Leave',
            'Mandatory/Forced Leave',
            'Sick Leave',
            'Maternity Leave',
            'Paternity Leave',
            'Special Privilege Leave',
            'Solo Parent Leave',
            'Study Leave',
            'Rehabilitation Privilege',
            'Special Leave Benefits for Women',
            'Special Emergency (Calamity) Leave',
            'Adoption Leave',
            'Others',
            'Service Credits',
        ])
            ->select('id', 'name')
            ->get();

        $employees = fn() => EmployeeResource::collection(
            Employee::with('personalInformation')->get()
        );

        return [
            'leaveTypes' => $leaveTypes,
            'employees' => $employees,
        ];
    }

    public function getOperatingUnits()
    {
        $operatingUnits = OperatingUnit::get();
        return $operatingUnits;
    }

    public function getSpecialLeaves()
    {
        // If request has getSpecialLeaves flag, return all special leaves
        if (request()->input('getSpecialLeaves')) {
            return SpecialLeave::all();
        }

        // Otherwise, filter by specific leave type (legacy support)
        $specialLeaves = SpecialLeave::where('leave_type_id', request()->input('specialLeaves'))->get();
        return $specialLeaves;
    }

    public function storeSingleEmployeeLeaveEntitlements(array $validated)
    {

        /*
            Logic to follow
            Fetch employee leave credit history by employee id and leave type id
        */
        $employeeLeaveCreditsHistory = EmployeeLeaveCreditsHistory::where('employee_id', $validated['employeeId']['id'])
            ->where('leave_id', $validated['leaveTypeId'])->latest()->first();

        if ($validated['specialLeaveId']) {
            $employeeLeaveCreditsHistory = EmployeeLeaveCreditsHistory::create([
                'employee_id' => $validated['employeeId']['id'],
                'leave_id' => $validated['leaveTypeId'],
                'total_earned' => 0,
                'credit_addition' => $validated['entitlement'],
                'credit_deduction' => 0,
                'balance' => $validated['entitlement'],
                'special_leave_id' => $validated['specialLeaveId'] ?? null,
                'document_type_number' => $validated['documentControlNumber'] ?? null,
                'expiration_date_from' => $validated['expirationDateFrom'] ?? null,
                'expiration_date_to' => $validated['expirationDateTo'] ?? null,
                'origin' => EmployeeLeaveCreditsHistory::ORIGIN_MANUAL_CREDIT,
                'remarks' => $validated['remarks'] ?? null,
            ]);
        } else {
            if ($employeeLeaveCreditsHistory) {
                $employeeLeaveCreditsHistory = EmployeeLeaveCreditsHistory::create([
                    'employee_id' => $validated['employeeId']['id'],
                    'leave_id' => $validated['leaveTypeId'],
                    'total_earned' => $employeeLeaveCreditsHistory->balance,
                    'credit_addition' => $validated['entitlement'],
                    'credit_deduction' => 0,
                    'balance' => $validated['entitlement'] + $employeeLeaveCreditsHistory->balance,
                    'special_leave_id' => $validated['specialLeaveId'] ?? null,
                    'document_type_number' => $validated['documentControlNumber'] ?? null,
                    'expiration_date_from' => $validated['expirationDateFrom'] ?? null,
                    'expiration_date_to' => $validated['expirationDateTo'] ?? null,
                    'origin' => EmployeeLeaveCreditsHistory::ORIGIN_MANUAL_CREDIT,
                    'remarks' => $validated['remarks'] ?? null,
                ]);
            } else {
                $employeeLeaveCreditsHistory = EmployeeLeaveCreditsHistory::create([
                    'employee_id' => $validated['employeeId']['id'],
                    'leave_id' => $validated['leaveTypeId'],
                    'total_earned' => 0,
                    'credit_addition' => $validated['entitlement'],
                    'credit_deduction' => 0,
                    'balance' => $validated['entitlement'],
                    'special_leave_id' => $validated['specialLeaveId'] ?? null,
                    'document_type_number' => $validated['documentControlNumber'] ?? null,
                    'expiration_date_from' => $validated['expirationDateFrom'] ?? null,
                    'expiration_date_to' => $validated['expirationDateTo'] ?? null,
                    'origin' => EmployeeLeaveCreditsHistory::ORIGIN_MANUAL_CREDIT,
                    'remarks' => $validated['remarks'] ?? null,
                ]);
            }
        }

        return $employeeLeaveCreditsHistory;
    }

    public function storeMultipleEmployeeLeaveEntitlements($validated)
    {

        foreach ($validated['employeeId'] as $employeeId) {

            $employeeLeaveCreditsHistory = EmployeeLeaveCreditsHistory::where('employee_id', $employeeId)
                ->where('leave_id', $validated['leaveTypeId'])->latest()->first();

            if ($validated['specialLeaveId']) {
                $employeeLeaveCreditsHistory = EmployeeLeaveCreditsHistory::create([
                    'employee_id' => $employeeId,
                    'leave_id' => $validated['leaveTypeId'],
                    'total_earned' => 0,
                    'credit_addition' => $validated['entitlement'],
                    'credit_deduction' => 0,
                    'balance' => $validated['entitlement'],
                    'special_leave_id' => $validated['specialLeaveId'] ?? null,
                    'document_type_number' => $validated['documentControlNumber'] ?? null,
                    'expiration_date_from' => $validated['expirationDateFrom'] ?? null,
                    'expiration_date_to' => $validated['expirationDateTo'] ?? null,
                    'origin' => EmployeeLeaveCreditsHistory::ORIGIN_MANUAL_CREDIT,
                    'remarks' => $validated['remarks'] ?? null,
                ]);
            } else {
                if ($employeeLeaveCreditsHistory) {
                    $employeeLeaveCreditsHistory = EmployeeLeaveCreditsHistory::create([
                        'employee_id' => $employeeId,
                        'leave_id' => $validated['leaveTypeId'],
                        'total_earned' => $employeeLeaveCreditsHistory->balance,
                        'credit_addition' => $validated['entitlement'],
                        'credit_deduction' => 0,
                        'balance' => $validated['entitlement'] + $employeeLeaveCreditsHistory->balance,
                        'special_leave_id' => $validated['specialLeaveId'] ?? null,
                        'document_type_number' => $validated['documentControlNumber'] ?? null,
                        'expiration_date_from' => $validated['expirationDateFrom'] ?? null,
                        'expiration_date_to' => $validated['expirationDateTo'] ?? null,
                        'origin' => EmployeeLeaveCreditsHistory::ORIGIN_MANUAL_CREDIT,
                        'remarks' => $validated['remarks'] ?? null,
                    ]);
                } else {
                    $employeeLeaveCreditsHistory = EmployeeLeaveCreditsHistory::create([
                        'employee_id' => $employeeId,
                        'leave_id' => $validated['leaveTypeId'],
                        'total_earned' => 0,
                        'credit_addition' => $validated['entitlement'],
                        'credit_deduction' => 0,
                        'balance' => $validated['entitlement'],
                        'special_leave_id' => $validated['specialLeaveId'] ?? null,
                        'document_type_number' => $validated['documentControlNumber'] ?? null,
                        'expiration_date_from' => $validated['expirationDateFrom'] ?? null,
                        'expiration_date_to' => $validated['expirationDateTo'] ?? null,
                        'origin' => EmployeeLeaveCreditsHistory::ORIGIN_MANUAL_CREDIT,
                        'remarks' => $validated['remarks'] ?? null,
                    ]);
                }
            }
        }

        return $employeeLeaveCreditsHistory;
    }

    public function countEmployeePerOperatingUnit(int $operatingUnitId)
    {
        $employees = Employee::where('operating_unit_id', $operatingUnitId)->get();

        return $employees->count();
    }

    public function getOperatingUnitDepartments(int $operatingUnitId)
    {
        $departments = Department::where('operating_unit_id', $operatingUnitId)->get();
        return $departments;
    }

    public function getEmployeesPerOperatingUnitAndDepartment(int $operatingUnitId, $departmentIds)
    {
        // Handle both single department ID and multiple department IDs
        $query = Employee::with(['personalInformation', 'jobStatus', 'department'])
            ->where('operating_unit_id', $operatingUnitId);

        // If departmentIds is an array, use whereIn, otherwise use where
        if (is_array($departmentIds)) {
            $query->whereIn('department_id', $departmentIds);
        } else {
            $query->where('department_id', $departmentIds);
        }

        // Arranged employees by lastname
        $employees = EmployeePerOperatingUnitAndDepartmentResource::collection(
            $query->orderBy(PersonalInformation::select('lastname')->whereColumn('employee_id', 'employees.id')->limit(1), 'ASC')->get()
        );
        return $employees;
    }

    public function getAuthorization()
    {
        $user = Auth::user();
        if (!$user || !$user->employee_id) {
            return false;
        }

        $employee = Employee::where('id', $user->employee_id)->first();
        if (!$employee) {
            return false;
        }

        $authorization = $employee->employeeDesignations()
            ->whereHas('designation', function ($q) {
                $q->whereIn('name', ['Chancellor', 'Executive Director', 'University President']);
            })
            ->exists();
        return $authorization;
    }

    public function canCertify()
    {
        $user = Auth::user();
        $canCertify = $user->can('leave.certify');
        if ($canCertify) {
            return true;
        }
        return false;
    }

    public function isDirectSupervisor()
    {
        $user = Auth::user();
        $employee = Employee::where('id', $user->employee_id)->first();

        if (!$employee) {
            return false;
        }

        // Check both direct supervisor relationship and mapped supervisor relationship
        $directSubs = Employee::where('immediate_supervisor_id', $employee->id)->exists();
        $mappedSubs = EmployeeSubordinate::where('supervisor_id', $employee->id)->exists();

        return $directSubs || $mappedSubs;
    }

    // public function getSubordinateIds(){
    //     $user = Auth::user();
    //     $employee = Employee::where('id', $user->employee_id)->first();

    //     $subordinateIds = collect();
    //     if ($employee) {
    //         $directSubs = Employee::where('immediate_supervisor_id', $employee->id)->pluck('id');
    //         $mappedSubs = EmployeeSubordinate::where('supervisor_id', $employee->id)->pluck('subordinate_id');
    //         $subordinateIds = $directSubs->merge($mappedSubs)->unique()->values();
    //     }

    //     return $subordinateIds;
    // }

    public function getLeaveTypes()
    {
        return Leave::whereIn('name', [
            'Vacation Leave',
            'Mandatory/Forced Leave',
            'Sick Leave',
            'Maternity Leave',
            'Paternity Leave',
            'Special Privilege Leave',
            'Solo Parent Leave',
            'Study Leave',
            'Rehabilitation Privilege',
            'Special Leave Benefits for Women',
            'Special Emergency (Calamity) Leave',
            'Adoption Leave',
            'Others'
        ])
            ->select('id', 'name')
            ->get();
    }

    public function leaveList()
    {
        $direction = request('direction') === 'Ascending' ? 'ASC' : 'DESC';

        $user = Auth::user();

        // dd(request()->all());

        $leaveApplications = LeaveApplication::with([
            'employee.personalInformation',
            'leave',
            'leaveDates',
            'specialLeaveCredit.specialLeave',
            'leaveCreditsHistory',
            'currentStatus' // 🔥 eager load the current leave status
        ])
            ->visibleTo(Auth::user())
            ->when(request('from') && request('to'), function ($query) {
                $query->where(function ($q) {
                    $q->whereBetween('from', [request('from'), request('to')])
                        ->orWhereBetween('to', [request('from'), request('to')]);
                });
            })
            ->when(request('leaveType'), function ($query) {
                $query->where('leave_id', request('leaveType'));
            })
            ->when(request('search'), function ($query) {
                $term = '%' . request('search') . '%';
                $query->whereHas('employee.personalInformation', function ($q) use ($term) {
                    $q->where('firstname', 'LIKE', $term)
                        ->orWhere('middlename', 'LIKE', $term)
                        ->orWhere('lastname', 'LIKE', $term)
                        ->orWhere('employee_number', 'LIKE', $term);
                });
            })
            ->when(request('operatingUnit'), function ($query) {
                $query->whereHas('employee', function ($q) {
                    $q->where('operating_unit_id', request('operatingUnit'));
                });
            })
            ->when(request('status'), function ($query) {
                $query->whereHas('currentStatus', function ($q) {
                    $q->where('leave_statuses.status', request('status'));
                });
            })
            // ->orderBy('created_at', 'asc')
            ->when(
                $user->hasRole(['campus_hr', 'campus_hr_staff']),
                function ($query) {
                    $query
                        ->orderByRaw("
                CASE
                    WHEN (
                        SELECT status
                        FROM leave_statuses
                        WHERE leave_statuses.leave_application_id = leave_applications.id
                        ORDER BY updated_at DESC, id DESC
                        LIMIT 1
                    ) IN ('for approval', 'for disapproval')
                    THEN 0
                    ELSE 1
                END
            ")
                        ->orderBy('updated_at', 'desc');
                },
                function ($query) {
                    $query->orderBy('created_at', 'desc');
                }
            )
            ->paginate(request('size', 10))
            ->through(function ($leave) {
                $leave->view_link = URL::signedRoute(
                    'hrmanagement.leave.viewLeaveApplication',
                    ['id' => $leave->id]
                );

                $leave->permissions = [
                    'canRecommend' => Auth::user()->can('recommend', $leave),
                    'canCertify' => Auth::user()->can('certify', $leave),
                    'canApprove' => Auth::user()->can('approve', $leave),
                ];

                // Attach current leave status info
                $leave->current_status = $leave->currentStatus?->status;
                $leave->current_acted_by = $leave->currentStatus?->acted_by;
                $leave->current_acted_at = $leave->currentStatus?->acted_at;

                return $leave;
            });

        return LeaveApplicationListResource::collection($leaveApplications);
    }

    public function updateLeaveApplicationStatus(array $validated)
    {
        $employeeLeave = LeaveApplication::with('leave')->findOrFail($validated['row_id']);

        $actedByEmployee = Auth()->user()->employee_id;
        $employee = Employee::where('id', $actedByEmployee)->first();

        $signatory_id = null;

        $status = $validated['status'];

        if ($status === 'for approval' || $status == 'for disapproval') {
            $signatory_id = Auth()->user()->employee_id;
        } else if ($status === 'certified') {
            //if certified need to get the HR head of the operating unit
            $signatory_id = $employee->hrHead()->id;

        } else if ($status === 'cancelled') {
            $signatory_id = null;
        } else {
            //Fetch the head of the operating unit

            $signatory_id = $employee->head()->id;
        }


        $leaveStatus = LeaveStatus::create([
            'leave_application_id' => $employeeLeave->id,
            'status' => strtolower($validated['status']),
            'remarks' => $validated['remarks'] ?? null,
            'acted_by' => auth()->user()->employee_id,
            'signatory_id' => $signatory_id,
            'acted_at' => now(),
        ]);

        $employeeLeave->update([
            'status' => $validated['status'],
            'remarks' => $validated['remarks'] ?? null,
            'current_status_id' => $leaveStatus->id,
        ]);

        return $employeeLeave->fresh();
    }

    protected function deductLeaveCredits($employeeLeave, $validated)
    {
        $employeeId = $employeeLeave->employee_id;
        $leaveName = $employeeLeave->leave->name;
        $creditsToDeduct = $employeeLeave->credits;

        if ($leaveName === 'Others') {
            $document_type_number = $employeeLeave->specialLeaveCredit->document_type_number;
            $employeeLeaveCreditsHistory = EmployeeLeaveCreditsHistory::where('document_type_number', $document_type_number)
                ->where('employee_id', $employeeId)
                ->latest()
                ->first();

            if ($employeeLeaveCreditsHistory) {
                EmployeeLeaveCreditsHistory::create([
                    'employee_id' => $employeeId,
                    'leave_id' => $employeeLeave->leave_id,
                    'total_earned' => $employeeLeaveCreditsHistory->balance,
                    'credit_addition' => 0,
                    'credit_deduction' => $creditsToDeduct,
                    'balance' => $employeeLeaveCreditsHistory->balance - $creditsToDeduct,
                    'special_leave_id' => $employeeLeaveCreditsHistory->special_leave_id,
                    'document_type_number' => $employeeLeaveCreditsHistory->document_type_number,
                    'expiration_date_from' => $employeeLeaveCreditsHistory->expiration_date_from,
                    'expiration_date_to' => $employeeLeaveCreditsHistory->expiration_date_to,
                ]);
            } else {
                throw new \Exception('Something went wrong: no previous credit history found for "Others" leave.');
            }
            return;
        }

        // Normal leaves: Vacation, Sick, Service, Mandatory/Forced
        $vacationLeave = Leave::where('name', 'Vacation Leave')->first();
        $sickLeave = Leave::where('name', 'Sick Leave')->first();
        $serviceCredits = Leave::where('name', 'Service Credits')->first();
        $forcedLeave = Leave::where('name', 'Mandatory/Forced Leave')->first();

        $vacationHistory = EmployeeLeaveCreditsHistory::where('employee_id', $employeeId)
            ->where('leave_id', $vacationLeave->id ?? null)
            ->latest()
            ->first();

        $sickHistory = EmployeeLeaveCreditsHistory::where('employee_id', $employeeId)
            ->where('leave_id', $sickLeave->id ?? null)
            ->latest()
            ->first();

        $serviceHistory = EmployeeLeaveCreditsHistory::where('employee_id', $employeeId)
            ->where('leave_id', $serviceCredits->id ?? null)
            ->latest()
            ->first();

        $remainingCredits = $creditsToDeduct;

        // Deduct VL
        if ($leaveName === 'Vacation Leave' && $vacationHistory) {
            $deduct = min($remainingCredits, $vacationHistory->balance);
            EmployeeLeaveCreditsHistory::create([
                'employee_id' => $employeeId,
                'leave_id' => $vacationLeave->id,
                'total_earned' => $vacationHistory->balance,
                'credit_addition' => 0,
                'credit_deduction' => $deduct,
                'balance' => $vacationHistory->balance - $deduct,
                'special_leave_id' => $validated['specialLeaveId'] ?? null,
                'document_type_number' => $validated['documentControlNumber'] ?? null,
                'expiration_date_from' => $validated['expirationDateFrom'] ?? null,
                'expiration_date_to' => $validated['expirationDateTo'] ?? null,
            ]);
            $remainingCredits -= $deduct;
        }

        // Deduct SL
        if ($leaveName === 'Sick Leave' && $sickHistory) {
            $deduct = min($remainingCredits, $sickHistory->balance);
            EmployeeLeaveCreditsHistory::create([
                'employee_id' => $employeeId,
                'leave_id' => $sickLeave->id,
                'total_earned' => $sickHistory->balance,
                'credit_addition' => 0,
                'credit_deduction' => $deduct,
                'balance' => $sickHistory->balance - $deduct,
                'special_leave_id' => $validated['specialLeaveId'] ?? null,
                'document_type_number' => $validated['documentControlNumber'] ?? null,
                'expiration_date_from' => $validated['expirationDateFrom'] ?? null,
                'expiration_date_to' => $validated['expirationDateTo'] ?? null,
            ]);
            $remainingCredits -= $deduct;
        }

        // Deduct remainder from Service Credits
        if ($remainingCredits > 0 && $serviceHistory) {
            $deduct = min($remainingCredits, $serviceHistory->balance);
            EmployeeLeaveCreditsHistory::create([
                'employee_id' => $employeeId,
                'leave_id' => $serviceCredits->id,
                'total_earned' => $serviceHistory->balance,
                'credit_addition' => 0,
                'credit_deduction' => $deduct,
                'balance' => $serviceHistory->balance - $deduct,
                'special_leave_id' => null,
                'document_type_number' => null,
                'expiration_date_from' => null,
                'expiration_date_to' => null,
            ]);
        }

        // Forced leave
        if ($leaveName === 'Mandatory/Forced Leave' && $vacationHistory) {
            $availableVL = $vacationHistory->balance;
            $availableFL = EmployeeLeaveCreditsHistory::where('employee_id', $employeeId)
                ->where('leave_id', $forcedLeave->id)
                ->latest()
                ->first();

            if ($availableVL > 0 && $availableFL) {
                $deduct = min($remainingCredits, $availableVL);
                EmployeeLeaveCreditsHistory::create([
                    'employee_id' => $employeeId,
                    'leave_id' => $vacationLeave->id,
                    'total_earned' => $availableVL,
                    'credit_addition' => 0,
                    'credit_deduction' => $deduct,
                    'balance' => $availableVL - $deduct,
                    'special_leave_id' => $validated['specialLeaveId'] ?? null,
                    'document_type_number' => $validated['documentControlNumber'] ?? null,
                    'expiration_date_from' => $validated['expirationDateFrom'] ?? null,
                    'expiration_date_to' => $validated['expirationDateTo'] ?? null,
                ]);
                EmployeeLeaveCreditsHistory::create([
                    'employee_id' => $employeeId,
                    'leave_id' => $forcedLeave->id,
                    'total_earned' => $availableFL->balance,
                    'credit_addition' => 0,
                    'credit_deduction' => $deduct,
                    'balance' => $availableFL->balance - $deduct,
                    'special_leave_id' => $validated['specialLeaveId'] ?? null,
                    'document_type_number' => $validated['documentControlNumber'] ?? null,
                    'expiration_date_from' => $validated['expirationDateFrom'] ?? null,
                    'expiration_date_to' => $validated['expirationDateTo'] ?? null,
                ]);
            }
        }
    }

    public function viewLeaveApplication(string $id)
    {
        $leaveApplication = LeaveApplication::with(
            'currentStatus',
            'employee.personalInformation',
            'leave',
            'specialLeaveCredit.specialLeave',
            'leaveCreditsHistory',
            'employee.department',
            'employee.operatingUnit',
            'employee.position.government_position',
            'employee.employeeDesignations.designation',
        )
            ->where('id', $id)
            ->first();

        // dd($leaveApplication);

        if ($leaveApplication) {
            $leaveApplication->print_link = URL::signedRoute('hrmanagement.leave.printLeaveApplication', ['id' => $leaveApplication->id]);
        }
        return $leaveApplication;
    }

    public function checkLeaveCreditsBalance(array $validated, int $employee_id)
    {
        $key = $validated['leaveType'] ?? null;
        $leaveType = Leave::where('name', $key)->first();

        if (!$leaveType) {
            return [
                'balance' => 0,
                'total_earned' => 0,
                'credit_addition' => 0,
                'credit_deduction' => 0,
                'has_credits' => false,
                'leave_type' => $key ?? 'Unknown',
            ];
        }

        // Get latest record for the requested leave type
        $employeeLeaveCreditsHistory = EmployeeLeaveCreditsHistory::where('employee_id', $employee_id)
            ->where('leave_id', $leaveType->id)
            ->latest()
            ->first();

        // Get latest Service Credit record
        $serviceCredit = Leave::where('name', 'Service Credits')->first();
        $serviceCreditHistory = $serviceCredit
            ? EmployeeLeaveCreditsHistory::where('employee_id', $employee_id)
                ->where('leave_id', $serviceCredit->id)
                ->latest()
                ->first()
            : null;

        // Default values
        $balance = $employeeLeaveCreditsHistory->balance ?? 0;
        $total_earned = $employeeLeaveCreditsHistory->total_earned ?? 0;
        $credit_addition = $employeeLeaveCreditsHistory->credit_addition ?? 0;
        $credit_deduction = $employeeLeaveCreditsHistory->credit_deduction ?? 0;

        // ✅ Teaching Employee Rule: Combine Service Credits with VL or SL
        if (in_array($leaveType->name, ['Vacation Leave', 'Sick Leave'])) {
            $serviceBalance = $serviceCreditHistory->balance ?? 0;

            if ($serviceBalance > 0) {
                if ($balance > 0) {
                    // Add service credits to VL/SL balance
                    $balance += $serviceBalance;
                } else {
                    // No VL/SL balance → use service credits as balance
                    $balance = $serviceBalance;
                }
            }
        }

        return [
            'balance' => $balance,
            'total_earned' => $total_earned,
            'credit_addition' => $credit_addition,
            'credit_deduction' => $credit_deduction,
            'has_credits' => $balance > 0,
            'leave_type' => $leaveType->name,
        ];
    }

    public function fetchEmployees()
    {
        $user = auth()->user();
        if ($user->hasRole('superadmin') || $user->hasRole('hr_director')) {
            $employees = fn() => EmployeeResource::collection(
                Employee::with('personalInformation')->get()
            );
        } else {
            $employees = fn() => EmployeeResource::collection(
                Employee::with('personalInformation')->where('operating_unit_id', $user->employee->operating_unit_id)->get()
            );
        }
        return $employees;
    }


    public function leaveTypes()
    {
        return Leave::whereIn('name', [
            'Vacation Leave',
            'Mandatory/Forced Leave',
            'Sick Leave',
            'Maternity Leave',
            'Paternity Leave',
            'Special Privilege Leave',
            'Solo Parent Leave',
            'Study Leave',
            'Rehabilitation Privilege',
            'Special Leave Benefits for Women',
            'Special Emergency (Calamity) Leave',
            'Adoption Leave',
            'Others'
        ])
            ->select('id', 'name')
            ->get();
    }


    public function storeLeaveApplication(array $validated)
    {
        $employee_id = $validated['employeeId'];

        // Load employee with supervisor and personal info
        $employee = Employee::with(['immediateSupervisor', 'personalInformation'])->findOrFail($employee_id);
        $personalInfo = $employee->personalInformation;

        $leaveType = Leave::where('name', $validated['leaveType'])->firstOrFail();

        // Check for overlapping leave applications
        $existingLeave = LeaveApplication::where('employee_id', $employee_id)
            ->where(function ($query) use ($validated) {
                $query->whereBetween('from', [$validated['from'], $validated['to']])
                    ->orWhereBetween('to', [$validated['from'], $validated['to']])
                    ->orWhere(function ($q) use ($validated) {
                        $q->where('from', '<=', $validated['from'])
                            ->where('to', '>=', $validated['to']);
                    });
            })
            ->first();

        if ($existingLeave) {
            throw new \Exception('You have already applied leave on this date.');
        }

        // Gender-based validation
        $gender = strtolower($employee->personalInformation->sex ?? '');
        if ($leaveType->name === 'Maternity Leave' && $gender === 'male') {
            throw new \Exception('You are not eligible for maternity leave.');
        }
        if ($leaveType->name === 'Paternity Leave' && $gender === 'female') {
            throw new \Exception('You are not eligible for paternity leave.');
        }

        // Calculate number of days including partial days
        $noOfDays = match ($validated['leave_duration']) {
            'Full Day' => 1,
            'Half Day - Morning', 'Half Day - Afternoon' => 0.5,
            default => $validated['workingDays']
        };

        $points = match ($validated['partial_days']) {
            'Start Date Only', 'End Date Only' => 0.5,
            'Start and End Date' => 1,
            default => 0
        };

        $totalCredits = $noOfDays + $points;

        // Check leave credits
        $employeeLeaveCreditsHistory = EmployeeLeaveCreditsHistory::where('employee_id', $employee_id)
            ->where('leave_id', $leaveType->id)
            ->latest()
            ->first();

        $allowedUnpaidLeaves = ['Vacation Leave', 'Sick Leave'];
        $hasSufficientCredits = $employeeLeaveCreditsHistory && $employeeLeaveCreditsHistory->balance >= $totalCredits;

        if (!$hasSufficientCredits && !in_array($leaveType->name, $allowedUnpaidLeaves)) {
            throw new \Exception('You do not have enough leave credits for this leave type.');
        }

        // Create leave application (pending)
        $leaveApplication = LeaveApplication::create([
            'employee_id' => $employee_id,
            'leave_id' => $leaveType->id,
            'location_type' => $validated['locationType'],
            'location_within_philippines' => $validated['location_within_philippines'],
            'location_abroad' => $validated['location_abroad'],
            'in_hospital' => $validated['in_hospital'],
            'out_hospital' => $validated['out_hospital'],
            'illness' => $validated['illness'],
            'sick_leave_type' => $validated['sickLeaveType'],
            'study_leave_application' => $validated['study_leave_application'] ?? null,
            'other_purposes' => $validated['otherPurpose'],
            'no_of_days' => $validated['workingDays'],
            'from' => $validated['from'],
            'to' => $validated['to'],
            'leave_duration' => $validated['leave_duration'],
            'commutation' => $validated['commutation'],
            'credits' => $totalCredits,
            // status is now handled via currentStatus relationship
        ]);


        // Create initial LeaveStatus (pending)
        $initialStatus = $leaveApplication->leaveStatuses()->create([
            'status' => 'pending',
            'acted_by' => null,
            'acted_at' => now(),
            'remarks' => null,
        ]);

        $leaveApplication->update([
            'current_status_id' => $initialStatus->id,
        ]);

        // Notify immediate supervisor
        if ($employee->immediateSupervisor?->id) {
            $employee->immediateSupervisor->notify(
                new LeaveApplicationNotification($leaveApplication, $personalInfo, $employee->immediateSupervisor)
            );
        }

        return [
            'leaveApplication' => $leaveApplication,
        ];
    }

    public function storeLeaveApplicationOthers(array $validated)
    {
        $employeeId = $validated['employeeId'];

        // Load employee with related info
        $employee = Employee::with('personalInformation', 'immediateSupervisor')->findOrFail($employeeId);
        $leave = Leave::where('name', $validated['leaveType'])->firstOrFail();
        $personalInformation = $employee->personalInformation;

        // Check for existing leave application in the same date range
        $existingLeave = LeaveApplication::where('employee_id', $employeeId)
            ->where('from', $validated['from'])
            ->where('to', $validated['to'])
            ->first();

        if ($existingLeave) {
            throw new \Exception('You have already applied leave on this date.');
        }

        // Calculate number of leave days
        $noOfDays = match ($validated['leave_duration']) {
            'Full Day' => 1,
            'Half Day - Morning', 'Half Day - Afternoon' => 0.5,
            default => $validated['workingDays'],
        };

        // Add partial days points
        $points = match ($validated['partial_days']) {
            'Start Date Only', 'End Date Only' => 0.5,
            'Start and End Date' => 1,
            default => 0,
        };
        $credits = $noOfDays + $points;

        $leaveApplication = DB::transaction(function () use ($employeeId, $leave, $validated, $credits) {

            $leaveApp = LeaveApplication::create([
                'employee_id' => $employeeId,
                'leave_id' => $leave->id,
                'special_leave_credit_id' => $validated['specialLeaveCreditId'] ?? null,
                'location_type' => $validated['locationType'] ?? null,
                'location_within_philippines' => $validated['location_within_philippines'] ?? null,
                'location_abroad' => $validated['location_abroad'] ?? null,
                'no_of_days' => $validated['workingDays'],
                'from' => $validated['from'],
                'to' => $validated['to'],
                'commutation' => $validated['commutation'] ?? null,
                'credits' => $credits,
            ]);

            $leaveApp->leaveStatuses()->create([
                'status' => 'pending',
                'acted_by' => $employeeId,
                'acted_at' => now(),
            ]);

            $leaveApp->update([
                'current_status_id' => $leaveApp->leaveStatuses()->latest()->first()->id,
            ]);

            return $leaveApp; // <-- return so it's accessible outside
        });

        // Notify immediate supervisor
        if ($employee->immediateSupervisor) {
            $immediateSupervisor = $employee->immediateSupervisor;
            $immediateSupervisor->notify(new LeaveApplicationNotification(
                $leaveApplication,
                $personalInformation,
                $immediateSupervisor
            ));
        }

        return $leaveApplication;
    }
}
