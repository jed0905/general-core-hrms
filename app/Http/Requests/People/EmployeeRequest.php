<?php

namespace App\Http\Requests\People;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared validation for the HR employee create/update forms.
 * Field names match the employees, educations and work_experiences columns.
 */
abstract class EmployeeRequest extends FormRequest
{
    /**
     * Rules for employee_number (differ between create and update).
     */
    abstract protected function employeeNumberRules(): array;

    protected function ignoreId(): ?int
    {
        return null;
    }

    public function rules(): array
    {
        $workRow = 'work_experience.*.';

        return [
            'employee_number' => $this->employeeNumberRules(),
            'emp_first_name' => ['required', 'string', 'max:255'],
            'emp_last_name' => ['required', 'string', 'max:255'],
            'emp_middle_name' => ['nullable', 'string', 'max:255'],
            'emp_suffix' => ['nullable', 'string', 'max:50'],
            'emp_birthday' => ['nullable', 'date'],
            'emp_sex' => ['required', 'string', Rule::in(['male', 'female', 'other'])],
            'emp_marital_status' => ['nullable', 'string'],
            'emp_nationality_id' => ['nullable', 'exists:nationalities,id'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'e_signature' => ['nullable', 'image', 'max:2048'],
            'street1' => ['nullable', 'string', 'max:255'],
            'street2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'country_id' => ['nullable', 'string', 'max:255'],
            'home_telephone_no' => ['nullable', 'string', 'max:50'],
            'mobile_no' => ['nullable', 'string', 'max:50'],
            'work_no' => ['nullable', 'string', 'max:50'],
            'work_email' => ['nullable', 'email', 'max:255', Rule::unique('employees', 'work_email')->ignore($this->ignoreId())],
            'other_email' => ['nullable', 'email', 'max:255'],
            'joined_date' => ['nullable', 'date'],
            'job_title_id' => ['nullable', 'exists:job_titles,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'employment_status_id' => ['nullable', 'exists:employment_statuses,id'],
            'supervisor_id' => ['nullable', 'exists:employees,id'],
            'status' => ['required', Rule::in(Employee::STATUSES)],

            // educations columns
            'education' => ['nullable', 'array'],
            'education.*.level' => ['nullable', 'string', 'max:255'],
            'education.*.institute' => ['nullable', 'string', 'max:255'],
            'education.*.major_specialization' => ['nullable', 'string', 'max:255'],
            'education.*.year' => ['nullable', 'integer'],
            'education.*.gpa_score' => ['nullable', 'string', 'max:255'],
            'education.*.start_date' => ['nullable', 'date'],
            'education.*.end_date' => ['nullable', 'date'],

            // work_experiences columns (company, job_title, from, to are NOT NULL).
            // A completely blank row is ignored; once any field is filled, the row must be complete.
            'work_experience' => ['nullable', 'array'],
            $workRow.'company' => ['nullable', 'string', 'max:255', "required_with:{$workRow}job_title,{$workRow}from,{$workRow}to,{$workRow}notes"],
            $workRow.'job_title' => ['nullable', 'string', 'max:255', "required_with:{$workRow}company,{$workRow}from,{$workRow}to,{$workRow}notes"],
            $workRow.'from' => ['nullable', 'date', "required_with:{$workRow}company,{$workRow}job_title"],
            $workRow.'to' => ['nullable', 'date', "required_with:{$workRow}company,{$workRow}job_title", "after_or_equal:{$workRow}from"],
            $workRow.'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'work_experience.*.company' => 'company',
            'work_experience.*.job_title' => 'job title',
            'work_experience.*.from' => 'start date',
            'work_experience.*.to' => 'end date',
            'work_experience.*.notes' => 'description',
        ];
    }
}
