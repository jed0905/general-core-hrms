<?php

namespace App\Http\Requests\Payroll;

use App\Models\StatutoryRuleVersion;
use App\Services\Payroll\Support\Decimal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A statutory rule version with its official source. Values are decimals in brackets/parameters.
 */
class StatutoryRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('statutoryRule') ? $this->user()->can('update', $this->route('statutoryRule')) : $this->user()->can('create', StatutoryRuleVersion::class);
    }

    public function rules(): array
    {
        return [
            'country_code' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
            'jurisdiction_code' => ['nullable', 'string', 'max:16'],
            'rule_key' => ['required', 'string', 'max:80'],
            'variant' => ['nullable', 'string', 'max:32'],
            'category' => ['required', Rule::in(StatutoryRuleVersion::CATEGORIES)],
            'name' => ['required', 'string', 'max:255'],
            'calculation_method' => ['required', 'string', 'max:40'],
            'applies_by' => ['required', Rule::in(StatutoryRuleVersion::APPLIES_BY)],
            'rounding_mode' => ['nullable', Rule::in(Decimal::ROUNDING_MODES)],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            'official_source' => ['required', 'string', 'max:255'],
            'source_url' => ['required', 'url', 'max:500'],
            'issuance_reference' => ['required', 'string', 'max:255'],
            'retrieved_at' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'parameters' => ['array'],
            'parameters.*.key' => ['required', 'string', 'max:64', 'distinct'],
            'parameters.*.value' => ['nullable', 'decimal:0,8'],
            'parameters.*.text_value' => ['nullable', 'string', 'max:255'],
            'brackets' => ['array'],
            'brackets.*.range_from' => ['required', 'decimal:0,4', 'min:0'],
            'brackets.*.range_to' => ['nullable', 'decimal:0,4'],
            'brackets.*.base_amount' => ['nullable', 'decimal:0,4'],
            'brackets.*.fixed_amount' => ['nullable', 'decimal:0,4'],
            'brackets.*.rate' => ['nullable', 'decimal:0,8'],
            'brackets.*.excess_over' => ['nullable', 'decimal:0,4'],
            'brackets.*.employee_amount' => ['nullable', 'decimal:0,4'],
            'brackets.*.employer_amount' => ['nullable', 'decimal:0,4'],
            'brackets.*.employee_rate' => ['nullable', 'decimal:0,8'],
            'brackets.*.employer_rate' => ['nullable', 'decimal:0,8'],
            'brackets.*.employer_extra_amount' => ['nullable', 'decimal:0,4'],
        ];
    }
}
