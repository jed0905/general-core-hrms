<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Assign a shift or rest day to employees for a date range.
 */
class AssignRosterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('attendance.roster.manage');
    }

    public function rules(): array
    {
        return [
            'employee_ids' => ['required', 'array', 'min:1', 'max:500'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from', 'before_or_equal:'.now()->addYear()->toDateString()],
            'shift_id' => ['nullable', 'exists:shifts,id'],
            'is_rest_day' => ['boolean'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ];
    }
}
