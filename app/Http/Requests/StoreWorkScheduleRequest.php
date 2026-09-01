<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('work_schedule.create');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:work_schedules,code'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],

            // Schedule Days validation
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
