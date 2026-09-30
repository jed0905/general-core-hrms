<?php

namespace App\Services;

use App\Models\Employee;

/**
 * Self-service view of an employee's own record.
 */
class EmployeeProfileService
{
    /**
     * The only columns an employee may change on their own record.
     * Identity, employment and organization fields stay HR-managed.
     */
    public const SELF_EDITABLE_FIELDS = [
        'street1',
        'street2',
        'city',
        'province',
        'zip_code',
        'country_id',
        'home_telephone_no',
        'mobile_no',
        'other_email',
    ];

    public function getProfile(Employee $employee): array
    {
        $employee->loadMissing([
            'jobTitle:id,job_title',
            'department:id,name',
            'location:id,city,province,address',
            'employmentStatus:id,name',
        ]);

        // Employee::supervisor() returns a subordinate (known issue), so resolve by id.
        $supervisor = $employee->supervisor_id
            ? Employee::select('id', 'emp_first_name', 'emp_last_name', 'employee_number')->find($employee->supervisor_id)
            : null;

        return [
            'id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'emp_first_name' => $employee->emp_first_name,
            'emp_middle_name' => $employee->emp_middle_name,
            'emp_last_name' => $employee->emp_last_name,
            'emp_suffix' => $employee->emp_suffix,
            'emp_birthday' => $employee->emp_birthday,
            'emp_sex' => $employee->emp_sex,
            'emp_marital_status' => $employee->emp_marital_status,
            'work_email' => $employee->work_email,
            'work_no' => $employee->work_no,
            'joined_date' => $employee->joined_date,
            'status' => $employee->status,
            'job_title' => $employee->jobTitle?->job_title,
            'department' => $employee->department?->name,
            'location' => $employee->location ? trim(implode(', ', array_filter([$employee->location->address, $employee->location->city]))) : null,
            'employment_status' => $employee->employmentStatus?->name,
            'supervisor' => $supervisor ? trim($supervisor->emp_first_name.' '.$supervisor->emp_last_name) : null,
            'contact' => $employee->only(self::SELF_EDITABLE_FIELDS),
        ];
    }

    /**
     * Only SELF_EDITABLE_FIELDS are written, whatever else $data contains.
     */
    public function updateContactDetails(Employee $employee, array $data): Employee
    {
        $employee->update(array_intersect_key($data, array_flip(self::SELF_EDITABLE_FIELDS)));

        return $employee;
    }
}
