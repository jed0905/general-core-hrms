<?php

namespace App\Http\Filters;

use App\Contracts\Filters\Filterable;
use App\Models\Employee;
use App\Models\LeaveApplication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class LeavesFilter
{

    protected $request;

    public function __construct($request)
    {
        $this->request = $request;
    }
    public function apply(Builder $query): Builder
    {
        // $employee_id = Employee::find($this->request['employee_id'] ?? null)->id;
        $from = $this->request['from'] ?? null;
        $to = $this->request['to'] ?? null;
        $leaveType = $this->request['leaveType'] ?? null;
        $status = $this->request['status'] ?? null;

        $query->when($from, function ($query) use ($from) {
            return $query->where('from', $from);
        });
        $query->when($to, function ($query) use ($to) {
            return $query->where('to', $to);
        });
        $query->when($leaveType, function ($query) use ($leaveType) {
            return $query->where('leave_id', $leaveType);
        });
        $query->when($status, function ($query) use ($status) {
            if (is_array($status) && !empty($status)) {
                return $query->whereIn('status', $status);
            } elseif (is_string($status) && !empty($status)) {
                return $query->where('status', $status);
            }
            return $query;
        });

        $query->orderBy('created_at', $this->request['direction'] ?? 'DESC');

        return $query;
    }
}
