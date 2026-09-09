<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeWorkScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('employee_work_schedule.assign');
    }

    public function rules(): array
    {
        return [
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['required', 'exists:employees,id'],
            'work_schedule_id' => ['required', 'exists:work_schedules,id'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_primary' => ['sometimes', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_ids.required' => 'Please select at least one employee.',
            'work_schedule_id.required' => 'Please select a work schedule pattern.',
            'effective_from.required' => 'The effective start date is required.',
            'effective_to.after_or_equal' => 'The end date must be on or after the effective start date.',
        ];
    }
}
