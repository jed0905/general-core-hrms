<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeJobDetailsFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {

        return [
            'date_hired' => 'required',
            'biometrics_id' => 'nullable',
            'biometrics_to_add' => 'nullable|array',
            'biometrics_to_add.*' => 'nullable|string',
            'biometrics_to_delete' => 'nullable|array',
            'biometrics_to_delete.*' => 'nullable|integer|exists:employee_biometric_ids,id',
            'detailed_at' => 'nullable|exists:operating_units,id',
            'operating_unit_id' => 'required|exists:operating_units,id',
            'department_id' => 'nullable|exists:departments,id',
            'employee_type' => 'nullable',
            'job_status_id' => 'nullable|exists:job_statuses,id',
            'position_id' => 'nullable|exists:positions,id',
            'parenthetical_title' => 'nullable|string',
            'salary_step_id' => 'nullable|exists:salary_steps,id',
            'custom_hourly_rate' => 'nullable|decimal:0,2',
            'custom_daily_rate' => 'nullable|decimal:0,2',

            // Additional rules for designation
            'designation' => 'nullable|array',
            'designation.*.designation_id' => 'nullable|exists:designations,id',
            'designation.*.assumption_date' => 'nullable|date',
            'designation.*.end_date' => 'nullable|date|after_or_equal:designation.*.assumption_date',

            // Supervisor details
            'immediate_supervisor_id' => 'nullable|exists:employees,id',
            'immediate_supervisor_designation' => 'nullable|exists:designations,id',
            'higher_supervisor_id' => 'nullable|exists:employees,id',
            'higher_supervisor_designation' => 'nullable|exists:designations,id',
        ];
    }
}
