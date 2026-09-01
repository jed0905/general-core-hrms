<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('work_schedule.update');
    }

    public function rules(): array
    {
        $scheduleId = $this->route('workSchedule')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('work_schedules', 'code')->ignore($scheduleId)],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],

            'days' => ['required', 'array', 'size:7'],
            'days.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'days.*.is_working_day' => ['boolean'],
            'days.*.shift_id' => [
                'nullable',
                'required_if:days.*.is_working_day,true',
                'exists:shifts,id',
            ],
        ];
    }
}
