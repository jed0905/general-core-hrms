<?php

namespace App\Services\Leave;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveApplication;
use App\Models\LeaveApplicationApproval;
use App\Models\LeaveApplicationAttachment;
use App\Models\LeaveApplicationComment;
use App\Models\LeaveApplicationDate;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class LeaveApplicationService
{
    protected LeavePolicyValidator $policyValidator;

    protected LeaveBalanceMovementService $balances;

    protected LeaveApprovalWorkflowResolver $workflowResolver;

    public function __construct(
        ?LeavePolicyValidator $policyValidator = null,
        ?LeaveBalanceMovementService $balances = null,
        ?LeaveApprovalWorkflowResolver $workflowResolver = null,
    ) {
        $this->policyValidator = $policyValidator ?? app(LeavePolicyValidator::class);
        $this->balances = $balances ?? app(LeaveBalanceMovementService::class);
        $this->workflowResolver = $workflowResolver ?? app(LeaveApprovalWorkflowResolver::class);
    }

    /**
     * Get paginated active applications for a specific employee.
     */
    public function getPaginatedApplications(int $employeeId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return LeaveApplication::with(['leaveType:id,name,code', 'dates', 'attachments'])
            ->where('employee_id', $employeeId)
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['leave_type_id']), fn ($q) => $q->where('leave_type_id', $filters['leave_type_id']))
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
            'dates',
        ])
            ->whereIn('status', ['pending', 'approved'])
            // Without a department there is no team to show, only the employee's own leave.
            ->when(
                empty($departmentIds),
                fn ($q) => $q->where('employee_id', $employeeId),
                fn ($q) => $q->whereHas('employee', fn ($e) => $e->whereIn('department_id', $departmentIds))
            )
            ->whereHas('dates', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('leave_date', [$startDate, $endDate]);
            })
            ->get()
            ->flatMap(function ($application) use ($employeeId) {
                $emp = $application->employee;

                // Resolve employee full name
                $fullName = trim(($emp?->emp_first_name ?? '').' '.($emp?->emp_last_name ?? ''));
                if (empty($fullName) && $emp?->user) {
                    $fullName = $emp->user->name;
                }
                if (empty($fullName)) {
                    $fullName = 'Employee #'.$application->employee_id;
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

        while (! empty($parentsToCheck)) {
            $children = $departments->whereIn('parent_id', $parentsToCheck);
            $parentsToCheck = $children->pluck('id')->toArray();

            if (! empty($parentsToCheck)) {
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
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['leave_type_id']), fn ($q) => $q->where('leave_type_id', $filters['leave_type_id']))
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
     * File a new leave application.
     *
     * Validation, balance reservation and approval assignment all happen in
     * one transaction on the locked balance row. Applications that need
     * approval get their approvers resolved from the workflow right away.
     */
    public function createApplication(int $employeeId, array $data, array $dates, array $files = []): LeaveApplication
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($employeeId, $data, $dates, $files, &$storedPaths) {
                $leaveTypeId = (int) $data['leave_type_id'];

                $this->balances->lock($employeeId, $leaveTypeId);
                $rule = $this->policyValidator->validate($employeeId, $leaveTypeId, $dates, $files);

                $requiresApproval = $rule ? $rule->requires_approval : true;
                $calculatedTotals = $this->calculateTotals($dates);

                $application = LeaveApplication::create([
                    'employee_id' => $employeeId,
                    'leave_type_id' => $leaveTypeId,
                    'reason' => $data['reason'] ?? null,
                    'total_days' => $calculatedTotals['total_days'],
                    'total_hours' => $calculatedTotals['total_hours'],
                    'status' => $requiresApproval ? LeaveApplication::STATUS_PENDING : LeaveApplication::STATUS_APPROVED,
                    'submitted_at' => now(),
                    'approved_at' => $requiresApproval ? null : now(),
                ]);

                $this->syncDates($application, $dates);

                if ($requiresApproval) {
                    $this->workflowResolver->createApprovals($application, $rule);
                    $this->balances->reservePending($employeeId, $leaveTypeId, $calculatedTotals['total_days']);
                    $application->recordStatus(LeaveApplication::STATUS_PENDING, $employeeId, 'Submitted');
                } else {
                    $this->balances->consumeDirectly($employeeId, $leaveTypeId, $calculatedTotals['total_days']);
                    $application->recordStatus(LeaveApplication::STATUS_APPROVED, $employeeId, 'Approved automatically: this leave type does not require approval.');
                }

                $storedPaths = $this->uploadAttachments($application, $files, $employeeId);

                return $application;
            });
        } catch (\Throwable $e) {
            $this->deleteStoredFiles($storedPaths);

            throw $e;
        }
    }

    /**
     * Edit a pending application, or correct and resubmit a returned one.
     *
     * Approval instances are not touched: they were fixed at submission.
     * A resubmitted application continues at the step that returned it.
     */
    public function updateApplication(LeaveApplication $application, array $data, array $dates, array $files = [], ?int $actorEmployeeId = null): LeaveApplication
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($application, $data, $dates, $files, $actorEmployeeId, &$storedPaths) {
                $application = LeaveApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
                $this->ensureEditable($application);

                $employeeId = (int) $application->employee_id;
                $oldTypeId = (int) $application->leave_type_id;
                $newTypeId = (int) $data['leave_type_id'];
                $wasReturned = $application->status === LeaveApplication::STATUS_RETURNED;

                $this->balances->lock($employeeId, $oldTypeId);
                $this->balances->lock($employeeId, $newTypeId);
                $this->policyValidator->validate($employeeId, $newTypeId, $dates, $files, $application);

                $calculatedTotals = $this->calculateTotals($dates);

                // A returned application already gave its reservation back.
                if (! $wasReturned) {
                    $this->balances->releasePending($employeeId, $oldTypeId, (float) $application->total_days);
                }

                $application->update([
                    'leave_type_id' => $newTypeId,
                    'reason' => $data['reason'] ?? null,
                    'total_days' => $calculatedTotals['total_days'],
                    'total_hours' => $calculatedTotals['total_hours'],
                    'status' => LeaveApplication::STATUS_PENDING,
                ]);

                $application->dates()->delete();
                $this->syncDates($application, $dates);

                $this->balances->reservePending($employeeId, $newTypeId, $calculatedTotals['total_days']);

                if ($wasReturned) {
                    $application->recordStatus(LeaveApplication::STATUS_PENDING, $actorEmployeeId, 'Resubmitted after correction');
                }

                $storedPaths = $this->uploadAttachments($application, $files, $actorEmployeeId ?? $employeeId);

                return $application;
            });
        } catch (\Throwable $e) {
            $this->deleteStoredFiles($storedPaths);

            throw $e;
        }
    }

    /**
     * Add a comment. Authorization (owner/assigned approver, open status) is the policy's job.
     */
    public function addComment(LeaveApplication $application, int $commenterEmployeeId, string $comment): LeaveApplicationComment
    {
        return $application->comments()->create([
            'commenter_id' => $commenterEmployeeId,
            'comment' => $comment,
        ]);
    }

    /**
     * Everything the detail page shows, in one load.
     */
    public function loadDetails(LeaveApplication $application): LeaveApplication
    {
        return $application->load([
            'employee:id,emp_first_name,emp_last_name,employee_number,department_id,supervisor_id',
            'employee.department:id,name',
            'leaveType:id,name,code',
            'dates' => fn ($q) => $q->orderBy('leave_date'),
            'attachments',
            'approvals' => fn ($q) => $q->orderBy('approval_order'),
            'statusHistories' => fn ($q) => $q->orderBy('acted_at')->orderBy('id'),
            'statusHistories.actor:id,emp_first_name,emp_last_name',
            'comments' => fn ($q) => $q->orderBy('created_at')->orderBy('id'),
            'comments.commenter:id,emp_first_name,emp_last_name',
        ]);
    }

    protected function syncDates(LeaveApplication $application, array $dates): void
    {
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
    }

    /**
     * Re-checked on the locked row, since the state may have changed after authorization.
     */
    protected function ensureEditable(LeaveApplication $application): void
    {
        $editable = $application->status === LeaveApplication::STATUS_RETURNED
            || ($application->status === LeaveApplication::STATUS_PENDING
                && ! $application->approvals()->where('status', '!=', LeaveApplicationApproval::STATUS_PENDING)->exists());

        if (! $editable) {
            throw ValidationException::withMessages([
                'application' => ['This leave application can no longer be edited.'],
            ]);
        }
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
        if (! $leaveType) {
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

        if (! $balance) {
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
        if (! empty($leaveType->min_notice_days) && $leaveType->min_notice_days > 0) {
            $earliestDate = collect($requestedDateStrings)->min();
            $noticeDays = now()->startOfDay()->diffInDays(Carbon::parse($earliestDate)->startOfDay(), false);

            if ($noticeDays < $leaveType->min_notice_days) {
                throw ValidationException::withMessages([
                    'dates' => "{$leaveType->name} requires at least {$leaveType->min_notice_days} day(s) advance notice.",
                ]);
            }
        }

        // Rule 4: Mandatory Attachment Check
        $hasAttachment = ! empty($files);
        if ($ignoreApplicationId && ! $hasAttachment) {
            $existingApp = LeaveApplication::find($ignoreApplicationId);
            $hasAttachment = $existingApp && $existingApp->attachments()->exists();
        }

        $requiresAttachment = $leaveType->requires_attachment ?? false;
        $attachmentMinDays = $leaveType->attachment_min_days ?? null;

        if (
            ($requiresAttachment || ($attachmentMinDays && $requestedTotals['total_days'] >= $attachmentMinDays))
            && ! $hasAttachment
        ) {
            throw ValidationException::withMessages([
                'attachments' => "An attachment/document is required when requesting {$leaveType->name}.",
            ]);
        }
    }

    /**
     * Cancel a pending application.
     */
    /**
     * Cancel an application on behalf of $actor.
     *
     * The caller's earlier authorization ran against a copy of the application
     * that may be stale (e.g. an approver approved it in the meantime), so the
     * cancel ability is evaluated again here against the locked row before
     * anything changes.
     *
     * @throws AuthorizationException when $actor may not cancel it in its current state
     */
    public function cancelApplication(LeaveApplication $application, User $actor): LeaveApplication
    {
        return DB::transaction(function () use ($application, $actor) {
            $application = LeaveApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();

            Gate::forUser($actor)->authorize('cancel', $application);

            $employeeId = (int) $application->employee_id;
            $leaveTypeId = (int) $application->leave_type_id;
            $days = (float) $application->total_days;

            match ($application->status) {
                // Pending days are reserved: give the reservation back.
                LeaveApplication::STATUS_PENDING => $this->balances->releasePending($employeeId, $leaveTypeId, $days),
                // Approved days were used: restore used and balance.
                LeaveApplication::STATUS_APPROVED => $this->balances->restoreUsed($employeeId, $leaveTypeId, $days),
                // A returned application already released its reservation.
                LeaveApplication::STATUS_RETURNED => null,
                default => throw ValidationException::withMessages([
                    'application' => ['This leave application can no longer be cancelled.'],
                ]),
            };

            $application->update([
                'status' => LeaveApplication::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

            // Steps nobody acted on will never be reached.
            $application->approvals()
                ->where('status', LeaveApplicationApproval::STATUS_PENDING)
                ->update(['status' => LeaveApplicationApproval::STATUS_SKIPPED]);

            $application->recordStatus(LeaveApplication::STATUS_CANCELLED, $actor->employee_id);

            return $application;
        });
    }

    /**
     * Store attachments on the private disk. Returns the stored paths so the
     * caller can clean them up if the transaction rolls back.
     */
    protected function uploadAttachments(LeaveApplication $application, array $files, ?int $uploadedByEmployeeId): array
    {
        $paths = [];

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $path = $file->store('leave_attachments', LeaveApplicationAttachment::DISK);
                $paths[] = $path;

                $application->attachments()->create([
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'file_size' => round($file->getSize() / 1024, 2), // KB
                    'uploaded_by' => $uploadedByEmployeeId ?? $application->employee_id,
                    'uploaded_at' => now(),
                ]);
            }
        }

        return $paths;
    }

    protected function deleteStoredFiles(array $paths): void
    {
        if (! empty($paths)) {
            Storage::disk(LeaveApplicationAttachment::DISK)->delete($paths);
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
