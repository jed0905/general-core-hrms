<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A legal entity (employer). Country is ISO 3166-1 alpha-2, currency ISO 4217.
 */
class LegalEntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payroll.config.manage');
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:32', Rule::unique('legal_entities', 'code')->ignore($this->route('legalEntity'))],
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
            'subdivision_code' => ['nullable', 'string', 'max:10'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'tax_identifier' => ['nullable', 'string', 'max:100'],
            'functional_currency' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'timezone' => ['required', 'timezone'],
            'locale' => ['required', 'string', 'max:16'],
            'fiscal_year_start_month' => ['required', 'integer', 'between:1,12'],
            'address' => ['nullable', 'string', 'max:255'],
            'localization_settings' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ];
    }
}
