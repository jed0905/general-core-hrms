<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('shift.create');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:shifts,code'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'break_start' => ['nullable', 'date_format:H:i'],
            'break_end' => ['nullable', 'date_format:H:i'],
            'required_hours' => ['required', 'numeric', 'min:0', 'max:24'],
            'is_overnight' => ['boolean'],
            'is_flexible' => ['boolean'],
        ];
    }
}
