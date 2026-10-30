<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Country statutory attributes; the fields are validated against the localization in the service.
 */
class StatutoryProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payroll.config.manage');
    }

    public function rules(): array
    {
        return [
            'country_code' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'fields' => ['array'],
        ];
    }
}
