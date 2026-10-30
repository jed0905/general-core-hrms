<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Generate a pay group calendar for one year.
 */
class GenerateCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payroll.config.manage');
    }

    public function rules(): array
    {
        return [
            'calendar_year' => ['required', 'integer', 'between:2000,2100'],
            'pay_date_offset_days' => ['required', 'integer', 'between:0,31'],
            'first_period_start' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
