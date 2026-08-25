<?php

namespace App\Http\Filters;

use Illuminate\Database\Eloquent\Builder;

class EmployeeFilter
{
    protected $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    /**
     * Apply filters to the given query.
     */
    public function apply(Builder $query): Builder
    {
        
        $search = request('search') ?? null;
        $employmentStatus = request('employment_status') ?? null;
        $department = request('department') ?? null;
        $operatingUnit = request('operating_unit') ?? null;
        $employeeType = request('employee_type') ?? null;

        // 🔍 Search filter
        $query->when($search, function ($query) use ($search) {
            $query->whereHas('personalInformation', function ($q) use ($search) {
                $q->where('employee_number', 'LIKE', "%{$search}%")
                    ->orWhere(function ($subQuery) use ($search) {
                        $subQuery
                            ->where('firstname', 'LIKE', "%{$search}%")
                            ->orWhere('middlename', 'LIKE', "%{$search}%")
                            ->orWhere('lastname', 'LIKE', "%{$search}%")
                            ->orWhereRaw("CONCAT(lastname, ', ', firstname) LIKE ?", ["%{$search}%"])
                            ->orWhereRaw("CONCAT(lastname, ' ', firstname) LIKE ?", ["%{$search}%"])
                            ->orWhereRaw("CONCAT(firstname, ' ', lastname) LIKE ?", ["%{$search}%"]);
                    });
            });
        });

        // 📌 Employment Status filter
        $query->when($employmentStatus, function ($query) use ($employmentStatus) {
            $query->whereHas('jobStatus', function ($q) use ($employmentStatus) {
                $q->where('id', $employmentStatus);
            });
        });

        // Department Filter
        $query->when($department, function ($query) use ($department) {
            $query->where('department_id', $department);
        });

        $query->when($operatingUnit, function ($query) use ($operatingUnit) {
            $query->where(function($q) use ($operatingUnit) {
                $q->where('operating_unit_id', $operatingUnit)
                  ->orWhere('detailed_at', $operatingUnit);
            });
        });

        // Employee Type Filter
        $query->when($employeeType, function ($query) use ($employeeType) {
            $query->where('employee_type', $employeeType);
        });
        

        return $query;
    }
}
