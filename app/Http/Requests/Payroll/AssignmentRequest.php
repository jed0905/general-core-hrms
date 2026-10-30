<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

/**
 * An employment assignment: pay group (entity + payroll country), work location and dates.
 */
class AssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payroll.config.manage');
    }

    public function rules(): array
    {
        return [
            'pay_group_id' => ['required', 'exists:pay_groups,id'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'work_country_code' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
            'work_subdivision_code' => ['nullable', 'string', 'max:10'],
            'jurisdiction_override_reason' => ['nullable', 'string', 'max:255'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            'is_primary' => ['boolean'],
        ];
    }
}
