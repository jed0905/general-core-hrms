<?php

namespace App\Services\Leave;

use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveApplication;
use App\Models\LeaveApplicationApproval;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveApprovalService
{
    /**
     * Get paginated pending applications assigned to the given approver employee.
     */
    public function getPendingApprovals(int $approverEmployeeId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return LeaveApplication::with([
            'employee:id,emp_first_name,emp_last_name,department_id',
            'employee.department:id,name',
            'leaveType:id,name,code',
            'dates',
            'attachments',
            'approvals' => fn($q) => $q->orderBy('approval_order'),
        ])
            ->where('status', 'pending')
            ->whereHas('approvals', function ($q) use ($approverEmployeeId) {
                $q->where('approver_id', $approverEmployeeId)
                    ->where('status', 'pending');
            })
            ->when(!empty($filters['leave_type_id']), fn($q) => $q->where('leave_type_id', $filters['leave_type_id']))
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Approve the leave application step.
     */
    public function approve(LeaveApplication $application, int $approverEmployeeId, ?string $remarks = null): LeaveApplication
    {
        return DB::transaction(function () use ($application, $approverEmployeeId, $remarks) {
            $approvalStep = $this->getPendingStepForApprover($application, $approverEmployeeId);

            // 1. Mark current step as approved
            $approvalStep->update([
                'status' => 'approved',
                'acted_at' => now(),
                'remarks' => $remarks,
            ]);

            // 2. Check if remaining pending approval steps exist
            $hasMorePendingSteps = $application->approvals()
                ->where('status', 'pending')
                ->where('approval_order', '>', $approvalStep->approval_order)
                ->exists();

            // 3. Final Approval: Update Application status & adjust Leave Balances
            if (!$hasMorePendingSteps) {
                $application->update([
                    'status' => 'approved',
                    'approved_at' => now(),
                ]);

                $balance = EmployeeLeaveBalance::where('employee_id', $application->employee_id)
                    ->where('leave_type_id', $application->leave_type_id)
                    ->first();

                if ($balance) {
                    $balance->decrement('pending', $application->total_days);
                    $balance->increment('used', $application->total_days);
                    $balance->decrement('balance', $application->total_days);
                }
            }

            return $application;
        });
    }

    /**
     * Reject the leave application step and application.
     */
    public function reject(LeaveApplication $application, int $approverEmployeeId, string $remarks): LeaveApplication
    {
        return DB::transaction(function () use ($application, $approverEmployeeId, $remarks) {
            $approvalStep = $this->getPendingStepForApprover($application, $approverEmployeeId);

            // 1. Mark current step as rejected
            $approvalStep->update([
                'status' => 'rejected',
                'acted_at' => now(),
                'remarks' => $remarks,
            ]);

            // 2. Reject main application
            $application->update([
                'status' => 'rejected',
                'rejected_at' => now(),
            ]);

            // 3. Revert Pending Balance
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
     * Return application to employee for revision/resubmission.
     */
    public function return(LeaveApplication $application, int $approverEmployeeId, string $remarks): LeaveApplication
    {
        return DB::transaction(function () use ($application, $approverEmployeeId, $remarks) {
            $approvalStep = $this->getPendingStepForApprover($application, $approverEmployeeId);

            // 1. Log remarks on step
            $approvalStep->update([
                'acted_at' => now(),
                'remarks' => $remarks,
            ]);

            // 2. Set application status to returned
            $application->update([
                'status' => 'returned',
            ]);

            // 3. Revert pending balance
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
     * Ensure the approver is authorized to act on the current pending step in sequence.
     */
    protected function getPendingStepForApprover(LeaveApplication $application, int $approverEmployeeId): LeaveApplicationApproval
    {
        if ($application->status !== 'pending') {
            throw ValidationException::withMessages([
                'application' => ['This leave application is no longer pending.'],
            ]);
        }

        // Retrieve current active pending step (lowest approval_order that is pending)
        $currentPendingStep = $application->approvals()
            ->where('status', 'pending')
            ->orderBy('approval_order', 'asc')
            ->first();

        if (!$currentPendingStep || (int) $currentPendingStep->approver_id !== $approverEmployeeId) {
            throw ValidationException::withMessages([
                'approval' => ['You are not authorized to act on this step or it is not your turn in the approval sequence.'],
            ]);
        }

        return $currentPendingStep;
    }
}
