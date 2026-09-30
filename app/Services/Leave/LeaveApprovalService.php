<?php

namespace App\Services\Leave;

use App\Models\LeaveApplication;
use App\Models\LeaveApplicationApproval;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveApprovalService
{
    public function __construct(
        protected LeaveBalanceMovementService $balances
    ) {}

    /**
     * Applications waiting on the given approver right now, i.e. where their
     * step is the lowest pending one. Later steps appear once it is their turn.
     */
    public function getPendingApprovals(int $approverEmployeeId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return LeaveApplication::with([
            'employee:id,emp_first_name,emp_last_name,department_id',
            'employee.department:id,name',
            'leaveType:id,name,code',
            'dates',
            'attachments',
            'approvals' => fn ($q) => $q->orderBy('approval_order'),
        ])
            ->where('status', LeaveApplication::STATUS_PENDING)
            ->whereHas('approvals', function ($q) use ($approverEmployeeId) {
                $q->where('approver_id', $approverEmployeeId)
                    ->where('status', LeaveApplicationApproval::STATUS_PENDING)
                    ->whereNotExists(function ($earlier) {
                        $earlier->from('leave_application_approvals as earlier')
                            ->whereColumn('earlier.leave_application_id', 'leave_application_approvals.leave_application_id')
                            ->where('earlier.status', LeaveApplicationApproval::STATUS_PENDING)
                            ->whereColumn('earlier.approval_order', '<', 'leave_application_approvals.approval_order');
                    });
            })
            ->when(! empty($filters['leave_type_id']), fn ($q) => $q->where('leave_type_id', $filters['leave_type_id']))
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Approve the approver's step; the last step approves the application
     * and moves pending -> used.
     */
    public function approve(LeaveApplication $application, int $approverEmployeeId, ?string $remarks = null): LeaveApplication
    {
        return DB::transaction(function () use ($application, $approverEmployeeId, $remarks) {
            $application = $this->lockApplication($application);
            $approvalStep = $this->getPendingStepForApprover($application, $approverEmployeeId);

            $approvalStep->update([
                'status' => LeaveApplicationApproval::STATUS_APPROVED,
                'acted_at' => now(),
                'remarks' => $remarks,
            ]);

            $hasMorePendingSteps = $application->approvals()
                ->where('status', LeaveApplicationApproval::STATUS_PENDING)
                ->exists();

            if (! $hasMorePendingSteps) {
                $application->update([
                    'status' => LeaveApplication::STATUS_APPROVED,
                    'approved_at' => now(),
                ]);

                $this->balances->consumePending(
                    (int) $application->employee_id,
                    (int) $application->leave_type_id,
                    (float) $application->total_days
                );

                $application->recordStatus(LeaveApplication::STATUS_APPROVED, $approverEmployeeId, $remarks);
            } else {
                $application->recordStatus(
                    LeaveApplication::STATUS_PENDING,
                    $approverEmployeeId,
                    trim("Step {$approvalStep->approval_order} approved. ".($remarks ?? ''))
                );
            }

            return $application;
        });
    }

    /**
     * Reject the application and release the reserved days.
     */
    public function reject(LeaveApplication $application, int $approverEmployeeId, string $remarks): LeaveApplication
    {
        return DB::transaction(function () use ($application, $approverEmployeeId, $remarks) {
            $application = $this->lockApplication($application);
            $approvalStep = $this->getPendingStepForApprover($application, $approverEmployeeId);

            $approvalStep->update([
                'status' => LeaveApplicationApproval::STATUS_REJECTED,
                'acted_at' => now(),
                'remarks' => $remarks,
            ]);

            // Later steps will never be reached.
            $application->approvals()
                ->where('status', LeaveApplicationApproval::STATUS_PENDING)
                ->update(['status' => LeaveApplicationApproval::STATUS_SKIPPED]);

            $application->update([
                'status' => LeaveApplication::STATUS_REJECTED,
                'rejected_at' => now(),
            ]);

            $this->balances->releasePending(
                (int) $application->employee_id,
                (int) $application->leave_type_id,
                (float) $application->total_days
            );

            $application->recordStatus(LeaveApplication::STATUS_REJECTED, $approverEmployeeId, $remarks);

            return $application;
        });
    }

    /**
     * Send the application back to the employee for correction. The step
     * stays pending, so the same approver acts again after resubmission.
     */
    public function return(LeaveApplication $application, int $approverEmployeeId, string $remarks): LeaveApplication
    {
        return DB::transaction(function () use ($application, $approverEmployeeId, $remarks) {
            $application = $this->lockApplication($application);
            $approvalStep = $this->getPendingStepForApprover($application, $approverEmployeeId);

            $approvalStep->update([
                'acted_at' => now(),
                'remarks' => $remarks,
            ]);

            $application->update([
                'status' => LeaveApplication::STATUS_RETURNED,
            ]);

            $this->balances->releasePending(
                (int) $application->employee_id,
                (int) $application->leave_type_id,
                (float) $application->total_days
            );

            $application->recordStatus(LeaveApplication::STATUS_RETURNED, $approverEmployeeId, $remarks);

            return $application;
        });
    }

    protected function lockApplication(LeaveApplication $application): LeaveApplication
    {
        return LeaveApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * Ensure the approver is authorized to act on the current pending step in sequence.
     */
    protected function getPendingStepForApprover(LeaveApplication $application, int $approverEmployeeId): LeaveApplicationApproval
    {
        if ($application->status !== LeaveApplication::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'application' => ['This leave application is no longer pending.'],
            ]);
        }

        // Retrieve current active pending step (lowest approval_order that is pending)
        $currentPendingStep = $application->approvals()
            ->where('status', LeaveApplicationApproval::STATUS_PENDING)
            ->orderBy('approval_order', 'asc')
            ->lockForUpdate()
            ->first();

        if (! $currentPendingStep || (int) $currentPendingStep->approver_id !== $approverEmployeeId) {
            throw ValidationException::withMessages([
                'approval' => ['You are not authorized to act on this step or it is not your turn in the approval sequence.'],
            ]);
        }

        return $currentPendingStep;
    }
}
