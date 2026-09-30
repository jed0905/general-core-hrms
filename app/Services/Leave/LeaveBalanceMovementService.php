<?php

namespace App\Services\Leave;

use App\Models\EmployeeLeaveBalance;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The single place where leave applications move balance counters.
 *
 * available = balance - pending. Filing reserves `pending`; final approval
 * moves pending -> used and subtracts from balance; rejecting, returning or
 * withdrawing releases pending; cancelling an approved application gives the
 * used days back.
 *
 * Every method must run inside the caller's DB transaction and locks the
 * balance row with lockForUpdate(). Employees without a balance row for the
 * leave type are left untouched, matching the existing behaviour.
 */
class LeaveBalanceMovementService
{
    public function lock(int $employeeId, int $leaveTypeId): ?EmployeeLeaveBalance
    {
        $this->ensureTransaction();

        return EmployeeLeaveBalance::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Filing (or resubmitting) an application that still needs approval.
     */
    public function reservePending(int $employeeId, int $leaveTypeId, float $days): void
    {
        $this->apply($employeeId, $leaveTypeId, pending: $days);
    }

    /**
     * Rejecting, returning or withdrawing a pending application.
     * Never releases more than is currently reserved.
     */
    public function releasePending(int $employeeId, int $leaveTypeId, float $days): void
    {
        $balance = $this->lock($employeeId, $leaveTypeId);

        if (! $balance) {
            return;
        }

        $release = min($days, (float) $balance->pending);

        $this->write($balance, pending: -$release);
    }

    /**
     * Final approval of a pending application.
     */
    public function consumePending(int $employeeId, int $leaveTypeId, float $days): void
    {
        $balance = $this->lock($employeeId, $leaveTypeId);

        if (! $balance) {
            return;
        }

        $release = min($days, (float) $balance->pending);

        $this->write($balance, pending: -$release, used: $days, balance: -$days);
    }

    /**
     * An application that needs no approval is used immediately.
     */
    public function consumeDirectly(int $employeeId, int $leaveTypeId, float $days): void
    {
        $this->apply($employeeId, $leaveTypeId, used: $days, balance: -$days);
    }

    /**
     * Cancelling an approved application gives the days back.
     */
    public function restoreUsed(int $employeeId, int $leaveTypeId, float $days): void
    {
        $balance = $this->lock($employeeId, $leaveTypeId);

        if (! $balance) {
            return;
        }

        $restore = min($days, (float) $balance->used);

        $this->write($balance, used: -$restore, balance: $restore);
    }

    private function apply(int $employeeId, int $leaveTypeId, float $pending = 0, float $used = 0, float $balance = 0): void
    {
        $row = $this->lock($employeeId, $leaveTypeId);

        if ($row) {
            $this->write($row, $pending, $used, $balance);
        }
    }

    private function write(EmployeeLeaveBalance $row, float $pending = 0, float $used = 0, float $balance = 0): void
    {
        $row->update([
            'pending' => round((float) $row->pending + $pending, 2),
            'used' => round((float) $row->used + $used, 2),
            'balance' => round((float) $row->balance + $balance, 2),
        ]);
    }

    private function ensureTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Leave balance movements must run inside a database transaction.');
        }
    }
}
