<?php

namespace App\Http\Filters;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveApplicationFilter
{
    protected $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function apply(Builder $query): Builder
    {
        $search = $this->request['search'] ?? null;
        $from = $this->request['from'] ?? null;
        $to = $this->request['to'] ?? null;
        $leaveType = $this->request['leaveType'] ?? null;
        $status = $this->request['status'] ?? null;
        $operatingUnit = $this->request['operatingUnit'] ?? null;

        $includeBalance = (bool)($this->request['includeBalance'] ?? false);
        $onlyPending = (bool)($this->request['onlyPending'] ?? false);
        $restrictToUserOperatingUnit = (bool)($this->request['restrictToUserOperatingUnit'] ?? false);

        $query->when($search, function ($query) use ($search) {
            return $query->whereHas('employee.personalInformation', function ($q) use ($search) {
                $q->where('firstname', 'LIKE', "%{$search}%")
                    ->orWhere('middlename', 'LIKE', "%{$search}%")
                    ->orWhere('lastname', 'LIKE', "%{$search}%")
                    ->orWhereRaw("CONCAT(lastname, ', ', firstname) LIKE ?", ["%{$search}%"])
                    ->orWhereRaw("CONCAT(lastname, ' ', firstname) LIKE ?", ["%{$search}%"])
                    ->orWhereRaw("CONCAT(firstname, ' ', lastname) LIKE ?", ["%{$search}%"]);
            });
        });

        $query->when($from, function ($query) use ($from) {
            return $query->where('leave_applications.from', $from);
        });
        $query->when($to, function ($query) use ($to) {
            return $query->where('leave_applications.to', $to);
        });
        $query->when($leaveType, function ($query) use ($leaveType) {
            return $query->where('leave_applications.leave_id', $leaveType);
        });
        $query->when($status, function ($query) use ($status) {
            return $query->where('leave_applications.status', $status);
        });

        // Scope by explicit operating unit id passed from UI
        $query->when($operatingUnit, function ($query) use ($operatingUnit) {
            return $query->whereHas('employee', function ($q) use ($operatingUnit) {
                $q->where('operating_unit_id', $operatingUnit);
            });
        });

        // Restrict listing to the current user's operating unit (for campus HR roles)
        if ($restrictToUserOperatingUnit) {
            $user = Auth::user();
            if ($user && $user->employee_id) {
                $employee = Employee::find($user->employee_id);
                if ($employee) {
                    $query->whereHas('employee', function ($q) use ($employee) {
                        $q->where('operating_unit_id', $employee->operating_unit_id);
                    });
                }
            }
        }

        if ($onlyPending) {
            $query->where('status', 'pending');
        }

        // if ($includeBalance) {
        //     $query
        //         ->leftJoin('employee_leave_credits_histories', function ($join) {
        //             $join->on('employee_leave_credits_histories.employee_id', '=', 'leave_applications.employee_id')
        //                 ->on('employee_leave_credits_histories.leave_id', '=', 'leave_applications.leave_id');
        //         })
        //         ->addSelect([
        //             'leave_applications.*',
        //             DB::raw('SUM(employee_leave_credits_histories.balance) as balance'),
        //         ])
        //         ->groupBy('leave_applications.id', 'employee_leave_credits_histories.leave_id');
        // }

        return $query->orderBy('leave_applications.id', 'desc');
    }
}
