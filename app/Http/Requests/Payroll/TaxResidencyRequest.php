<?php

namespace App\Http\Requests\Payroll;

use App\Models\EmployeeTaxResidency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Effective-dated tax residency with evidence (never derived from nationality).
 */
class TaxResidencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payroll.config.manage');
    }

    public function rules(): array
    {
        return [
            'country_code' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
            'residency_status' => ['required', Rule::in(EmployeeTaxResidency::STATUSES)],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
