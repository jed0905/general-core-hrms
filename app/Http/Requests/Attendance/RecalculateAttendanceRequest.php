<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Recalculate attendance days for employees and dates.
 */
class RecalculateAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('attendance.process');
    }

    public function rules(): array
    {
        return [
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from', function ($attribute, $value, $fail) {
                if (strtotime($value) - strtotime((string) $this->input('date_from')) > 62 * 86400) {
                    $fail('Recalculate at most 62 days at a time.');
                }
            }],
            'employee_ids' => ['nullable', 'array', 'max:2000'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
        ];
    }
}
