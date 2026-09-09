<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('holiday.create');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:holidays,code'],
            'date' => ['required', 'date'],
            'type' => ['required', 'string', 'in:regular,special_non_working,special_working,company'],
            'is_paid' => ['sometimes', 'boolean'],
            'is_working_day' => ['sometimes', 'boolean'],
            'is_recurring' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ];
    }
}
