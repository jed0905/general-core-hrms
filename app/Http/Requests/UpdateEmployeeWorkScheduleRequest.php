<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeWorkScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('employee_work_schedule.update');
    }

    public function rules(): array
    {
        return [
            'work_schedule_id' => ['required', 'exists:work_schedules,id'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_primary' => ['sometimes', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }
}
