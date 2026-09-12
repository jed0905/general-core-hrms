<?php

namespace App\Services;

use App\Models\EmployeeLeaveBalance;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class LeaveBalanceService
{
    /**
     * Retrieve paginated leave balances with filters.
     */
    public function getPaginatedBalances(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $year = $filters['year'] ?? null;

        return EmployeeLeaveBalance::with([
            'employee:id,emp_first_name,emp_last_name,employee_number',
            'leaveType:id,name,code'
        ])
            ->when(!empty($filters['search']), function ($query) use ($filters) {
                $query->whereHas('employee', function ($q) use ($filters) {
                    $q->where('emp_first_name', 'like', "%{$filters['search']}%")
                        ->orWhere('emp_last_name', 'like', "%{$filters['search']}%")
                        ->orWhere('employee_number', 'like', "%{$filters['search']}%");
                });
            })
            ->when(!empty($filters['leave_type_id']), fn($q) => $q->where('leave_type_id', $filters['leave_type_id']))
            ->when($year, fn($q) => $q->whereYear('as_of_date', $year))
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Initialize a new leave balance for an employee.
     */
    public function createBalance(array $data)
    {
        return EmployeeLeaveBalance::create([
            'employee_id' => $data['employee_id'],
            'leave_type_id' => $data['leave_type_id'],
            'balance' => $data['balance'],
            'used' => $data['used'] ?? 0,
            'pending' => $data['pending'] ?? 0,
            'as_of_date' => $data['as_of_date'],
        ]);
    }

    /**
     * Update an existing leave balance record.
     */
    public function updateBalance(EmployeeLeaveBalance $leaveBalance, array $data)
    {
        $leaveBalance->update([
            'balance' => $data['balance'],
            'used' => $data['used'],
            'pending' => $data['pending'],
            'as_of_date' => $data['as_of_date'],
        ]);

        return $leaveBalance;
    }

    /**
     * Manually adjust a leave balance (Add / Deduct) inside a transaction.
     */
    public function adjustBalance(int $balanceId, string $type, float $days, string $reason, ?int $adjustedByUserId = null)
    {
        return DB::transaction(function () use ($balanceId, $type, $days, $reason, $adjustedByUserId) {
            $balance = EmployeeLeaveBalance::lockForUpdate()->findOrFail($balanceId);

            $adjustment = ($type === 'add') ? $days : -$days;
            $balance->balance += $adjustment;
            $balance->save();

            if (method_exists($balance, 'histories')) {
                $balance->histories()->create([
                    'adjusted_by' => $adjustedByUserId ?? auth()->id(),
                    'type' => $type,
                    'days' => $days,
                    'reason' => $reason,
                ]);
            }

            return $balance;
        });
    }
}
