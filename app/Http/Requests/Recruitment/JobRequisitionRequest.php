<?php

namespace App\Http\Requests\Recruitment;

use App\Models\JobRequisition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared rules for creating and editing a draft requisition. Only users with
 * organization-wide access may name another requester; everyone else always
 * requests as themselves.
 */
abstract class JobRequisitionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->user()->can('recruitment.requisition.view')) {
            $this->merge(['requested_by_employee_id' => $this->user()->employee_id]);
        }
    }

    public function rules(): array
    {
        return [
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'job_title_id' => ['required', 'integer', 'exists:job_titles,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'employment_status_id' => ['nullable', 'integer', 'exists:employment_statuses,id'],
            'positions' => ['required', 'integer', 'min:1', 'max:999'],
            'reason' => ['required', Rule::in(JobRequisition::REASONS)],
            'replaced_employee_id' => ['nullable', 'required_if:reason,'.JobRequisition::REASON_REPLACEMENT, 'integer', 'exists:employees,id'],
            'justification' => ['required', 'string', 'max:5000'],
            'target_start_date' => ['nullable', 'date'],
            'requested_by_employee_id' => ['required', 'integer', 'exists:employees,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'department_id' => 'department',
            'job_title_id' => 'job title',
            'location_id' => 'location',
            'employment_status_id' => 'employment status',
            'replaced_employee_id' => 'replaced employee',
            'requested_by_employee_id' => 'requested by',
        ];
    }

    public function messages(): array
    {
        return ['replaced_employee_id.required_if' => 'Select the employee being replaced.'];
    }
}
