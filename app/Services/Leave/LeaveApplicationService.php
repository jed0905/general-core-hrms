<?php

namespace App\Services\Leave;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveApplication;
use App\Models\LeaveApplicationDate;
use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveApplicationService
{
    protected LeavePolicyValidator $policyValidator;

    public function __construct(?LeavePolicyValidator $policyValidator = null)
    {
        $this->policyValidator = $policyValidator ?? app(LeavePolicyValidator::class);
    }
    /**
     * Get paginated active applications for a specific employee.
     */
    public function getPaginatedApplications(int $employeeId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return LeaveApplication::with(['leaveType:id,name,code', 'dates', 'attachments'])
            ->where('employee_id', $employeeId)
            ->when(!empty($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->when(!empty($filters['leave_type_id']), fn($q) => $q->where('leave_type_id', $filters['leave_type_id']))
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Fetch team leave events for calendar visualization.
     */
    public function getTeamLeaveCalendarEvents(int $employeeId, string $startDate, string $endDate): array
    {
        $employee = Employee::find($employeeId);

        // Get the employee's department ID plus all sub-department IDs recursively
        $departmentIds = [];
        if ($employee && $employee->department_id) {
            $departmentIds = $this->getDepartmentWithDescendants($employee->department_id);
        }

        return LeaveApplication::with([
            'employee:id,emp_first_name,emp_last_name',
            'employee.user:id',
            'leaveType:id,name,code',
            'dates'
        ])
            ->whereIn('status', ['pending', 'approved'])
            ->whereHas('employee', function ($q) use ($departmentIds) {
                if (!empty($departmentIds)) {
                    $q->whereIn('department_id', $departmentIds);
                }
            })
            ->whereHas('dates', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('leave_date', [$startDate, $endDate]);
            })
            ->get()
            ->flatMap(function ($application) use ($employeeId) {
                $emp = $application->employee;

                // Resolve employee full name
                $fullName = trim(($emp?->emp_first_name ?? '') . ' ' . ($emp?->emp_last_name ?? ''));
                if (empty($fullName) && $emp?->user) {
                    $fullName = $emp->user->name;
                }
                if (empty($fullName)) {
                    $fullName = 'Employee #' . $application->employee_id;
                }

                return $application->dates->map(function ($date) use ($application, $employeeId, $fullName) {
                    $isSelf = (int) $application->employee_id === (int) $employeeId;

                    return [
                        'id' => $application->id,
                        'title' => $isSelf ? 'My Leave' : $fullName,
                        'employee_name' => $fullName,
                        'date' => $date->leave_date->toDateString(),
                        'leave_type' => $application->leaveType?->code ?? 'LV',
                        'leave_type_name' => $application->leaveType?->name ?? 'Leave',
                        'status' => $application->status,
                        'is_self' => $isSelf,
                        'duration_type' => $date->duration_type,
                    ];
                });
            })
            ->values()
            ->toArray();
    }

    /**
     * Recursively retrieves a department ID and all of its descendant sub-department IDs.
     */
    protected function getDepartmentWithDescendants(int $departmentId): array
    {
        $departments = Department::select('id', 'parent_id')->get();

        $departmentIds = [$departmentId];
        $parentsToCheck = [$departmentId];

        while (!empty($parentsToCheck)) {
            $children = $departments->whereIn('parent_id', $parentsToCheck);
            $parentsToCheck = $children->pluck('id')->toArray();

            if (!empty($parentsToCheck)) {
                $departmentIds = array_merge($departmentIds, $parentsToCheck);
            }
        }

        return array_unique($departmentIds);
    }

    /**
     * Get completed/archived application history for an employee.
     */
    public function getApplicationHistory(int $employeeId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return LeaveApplication::with(['leaveType:id,name,code', 'dates', 'attachments'])
            ->where('employee_id', $employeeId)
            ->whereIn('status', ['approved', 'rejected', 'cancelled'])
            ->when(!empty($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->when(!empty($filters['leave_type_id']), fn($q) => $q->where('leave_type_id', $filters['leave_type_id']))
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Fetch active leave balances for an employee.
     */
    public function getEmployeeBalances(int $employeeId)
    {
        return EmployeeLeaveBalance::with('leaveType:id,name,code')
            ->where('employee_id', $employeeId)
            ->get();
    }

    /**
     * Create a new leave application with date breakdowns and attachments.
     */
    public function createApplication(int $employeeId, array $data, array $dates, array $files = []): LeaveApplication
    {
        // 1. Run Policy Rule Validation
        $rule = $this->policyValidator->validate($employeeId, $data['leave_type_id'], $dates, $files);

        // Determine if approval is required by the active rule
        $requiresApproval = $rule ? $rule->requires_approval : true;
        $initialStatus = $requiresApproval ? 'pending' : 'approved';

        return DB::transaction(function () use ($employeeId, $data, $dates, $files, $initialStatus, $requiresApproval) {
            $calculatedTotals = $this->calculateTotals($dates);

            // 2. Create main application record
            $application = LeaveApplication::create([
                'employee_id' => $employeeId,
                'leave_type_id' => $data['leave_type_id'],
                'reason' => $data['reason'] ?? null,
                'total_days' => $calculatedTotals['total_days'],
                'total_hours' => $calculatedTotals['total_hours'],
                'status' => $initialStatus,
                'submitted_at' => now(),
            ]);

            // 3. Attach application dates
            foreach ($dates as $dateItem) {
                $dayFraction = $this->getDayFraction($dateItem['duration_type'], $dateItem['hours'] ?? null);
                $hours = $this->getHours($dateItem['duration_type'], $dateItem['hours'] ?? null);

                $application->dates()->create([
                    'leave_date' => $dateItem['leave_date'],
                    'duration_type' => $dateItem['duration_type'],
                    'hours' => $hours,
                    'day_fraction' => $dayFraction,
                    'start_time' => $dateItem['start_time'] ?? null,
                    'end_time' => $dateItem['end_time'] ?? null,
                    'is_paid' => $dateItem['is_paid'] ?? true,
                ]);
            }

            // 4. Upload attachments
            if (!empty($files)) {
                $this->uploadAttachments($application, $files, $employeeId);
            }

            // 5. Update Employee Leave Balance counters
            $balance = EmployeeLeaveBalance::where('employee_id', $employeeId)
                ->where('leave_type_id', $data['leave_type_id'])
                ->first();

            if ($balance) {
                if ($requiresApproval) {
                    $balance->increment('pending', $calculatedTotals['total_days']);
                } else {
                    $balance->increment('used', $calculatedTotals['total_days']);
                    $balance->decrement('balance', $calculatedTotals['total_days']);
                }
            }

            return $application;
        });
    }

    /**
     * Update an existing pending leave application.
     */
    public function updateApplication(LeaveApplication $application, array $data, array $dates, array $files = []): LeaveApplication
    {
        // Run Policy Rule Validation
        $this->policyValidator->validate($application->employee_id, $data['leave_type_id'], $dates, $files);

        return DB::transaction(function () use ($application, $data, $dates, $files) {
            $oldTotalDays = $application->total_days;
            $calculatedTotals = $this->calculateTotals($dates);

            $application->update([
                'leave_type_id' => $data['leave_type_id'],
                'reason' => $data['reason'] ?? null,
                'total_days' => $calculatedTotals['total_days'],
                'total_hours' => $calculatedTotals['total_hours'],
            ]);

            $application->dates()->delete();
            foreach ($dates as $dateItem) {
                $application->dates()->create([
                    'leave_date' => $dateItem['leave_date'],
                    'duration_type' => $dateItem['duration_type'],
                    'hours' => $this->getHours($dateItem['duration_type'], $dateItem['hours'] ?? null),
                    'day_fraction' => $this->getDayFraction($dateItem['duration_type'], $dateItem['hours'] ?? null),
                    'start_time' => $dateItem['start_time'] ?? null,
                    'end_time' => $dateItem['end_time'] ?? null,
                    'is_paid' => $dateItem['is_paid'] ?? true,
                ]);
            }

            if (!empty($files)) {
                $this->uploadAttachments($application, $files, $application->employee_id);
            }

            $balance = EmployeeLeaveBalance::where('employee_id', $application->employee_id)
                ->where('leave_type_id', $data['leave_type_id'])
                ->first();

            if ($balance) {
                $diff = $calculatedTotals['total_days'] - $oldTotalDays;
                if ($diff > 0) {
                    $balance->increment('pending', $diff);
                } elseif ($diff < 0) {
                    $balance->decrement('pending', abs($diff));
                }
            }

            return $application;
        });
    }

    /**
     * Enforce leave policy constraints before allowing database creation or updates.
     */
    protected function validatePolicyRules(int $employeeId, int $leaveTypeId, array $dates, array $files = [], ?int $ignoreApplicationId = null): void
    {
        if (empty($dates)) {
            throw ValidationException::withMessages([
                'dates' => 'At least one leave date must be specified.',
            ]);
        }

        $leaveType = LeaveType::find($leaveTypeId);
        if (!$leaveType) {
            throw ValidationException::withMessages([
                'leave_type_id' => 'The selected leave type is invalid.',
            ]);
        }

        $requestedTotals = $this->calculateTotals($dates);
        $requestedDateStrings = array_column($dates, 'leave_date');

        // Rule 1: Prevent Overlapping/Duplicate Dates
        $overlappingCount = LeaveApplicationDate::whereHas('application', function ($q) use ($employeeId, $ignoreApplicationId) {
            $q->where('employee_id', $employeeId)
                ->whereIn('status', ['pending', 'approved']);

            if ($ignoreApplicationId) {
                $q->where('id', '!=', $ignoreApplicationId);
            }
        })
            ->whereIn('leave_date', $requestedDateStrings)
            ->count();

        if ($overlappingCount > 0) {
            throw ValidationException::withMessages([
                'dates' => 'One or more of the selected dates overlap with an existing pending or approved leave application.',
            ]);
        }

        // Rule 2: Balance Sufficiency Check
        $balance = EmployeeLeaveBalance::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->first();

        if (!$balance) {
            throw ValidationException::withMessages([
                'leave_type_id' => 'You do not have an active balance assigned for this leave type.',
            ]);
        }

        // Calculate available balance (if editing, restore the previous application's days for calculation)
        $availableBalance = $balance->balance - $balance->pending;
        if ($ignoreApplicationId) {
            $existingApp = LeaveApplication::find($ignoreApplicationId);
            if ($existingApp && (int) $existingApp->leave_type_id === (int) $leaveTypeId) {
                $availableBalance += $existingApp->total_days;
            }
        }

        if ($requestedTotals['total_days'] > $availableBalance) {
            throw ValidationException::withMessages([
                'leave_type_id' => "Insufficient leave balance. Requested: {$requestedTotals['total_days']} day(s), Available: {$availableBalance} day(s).",
            ]);
        }

        // Rule 3: Advance Notice Requirement
        if (!empty($leaveType->min_notice_days) && $leaveType->min_notice_days > 0) {
            $earliestDate = collect($requestedDateStrings)->min();
            $noticeDays = now()->startOfDay()->diffInDays(Carbon::parse($earliestDate)->startOfDay(), false);

            if ($noticeDays < $leaveType->min_notice_days) {
                throw ValidationException::withMessages([
                    'dates' => "{$leaveType->name} requires at least {$leaveType->min_notice_days} day(s) advance notice.",
                ]);
            }
        }

        // Rule 4: Mandatory Attachment Check
        $hasAttachment = !empty($files);
        if ($ignoreApplicationId && !$hasAttachment) {
            $existingApp = LeaveApplication::find($ignoreApplicationId);
            $hasAttachment = $existingApp && $existingApp->attachments()->exists();
        }

        $requiresAttachment = $leaveType->requires_attachment ?? false;
        $attachmentMinDays = $leaveType->attachment_min_days ?? null;

        if (
            ($requiresAttachment || ($attachmentMinDays && $requestedTotals['total_days'] >= $attachmentMinDays))
            && !$hasAttachment
        ) {
            throw ValidationException::withMessages([
                'attachments' => "An attachment/document is required when requesting {$leaveType->name}.",
            ]);
        }
    }

    /**
     * Cancel a pending application.
     */
    public function cancelApplication(LeaveApplication $application): LeaveApplication
    {
        return DB::transaction(function () use ($application) {
            $application->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            // Revert pending balance
            $balance = EmployeeLeaveBalance::where('employee_id', $application->employee_id)
                ->where('leave_type_id', $application->leave_type_id)
                ->first();

            if ($balance && $balance->pending >= $application->total_days) {
                $balance->decrement('pending', $application->total_days);
            }

            return $application;
        });
    }

    /**
     * Upload and store attachment files.
     */
    protected function uploadAttachments(LeaveApplication $application, array $files, int $uploadedByEmployeeId): void
    {
        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $path = $file->store('leave_attachments', 'public');

                $application->attachments()->create([
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'file_size' => round($file->getSize() / 1024, 2), // KB
                    'uploaded_by' => $uploadedByEmployeeId,
                    'uploaded_at' => now(),
                ]);
            }
        }
    }

    /**
     * Calculate cumulative total days and hours from specified dates.
     */
    protected function calculateTotals(array $dates): array
    {
        $totalDays = 0.0;
        $totalHours = 0.0;

        foreach ($dates as $d) {
            $fraction = $this->getDayFraction($d['duration_type'], $d['hours'] ?? null);
            $hours = $this->getHours($d['duration_type'], $d['hours'] ?? null);

            $totalDays += $fraction;
            $totalHours += $hours;
        }

        return [
            'total_days' => $totalDays,
            'total_hours' => $totalHours,
        ];
    }

    private function getDayFraction(string $type, ?float $hours = null): float
    {
        return match ($type) {
            'full_day' => 1.0,
            'half_day' => 0.5,
            'hours' => round(($hours ?? 0) / 8.0, 4),
            default => 1.0,
        };
    }

    private function getHours(string $type, ?float $hours = null): float
    {
        return match ($type) {
            'full_day' => 8.0,
            'half_day' => 4.0,
            'hours' => (float) ($hours ?? 0),
            default => 8.0,
        };
    }
}
