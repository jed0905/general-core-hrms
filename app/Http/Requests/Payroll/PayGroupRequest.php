<?php

namespace App\Http\Requests\Payroll;

use App\Models\PayGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A pay group: frequency, pay currency, payroll country and working-time basis (no defaults for the divisors).
 */
class PayGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payroll.config.manage');
    }

    public function rules(): array
    {
        return [
            'legal_entity_id' => ['required', 'exists:legal_entities,id'],
            'code' => ['required', 'string', 'max:32', Rule::unique('pay_groups', 'code')->ignore($this->route('payGroup'))],
            'name' => ['required', 'string', 'max:255'],
            'payroll_country_code' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
            'pay_frequency' => ['required', Rule::in(PayGroup::FREQUENCIES)],
            'pay_currency' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'hours_per_day' => ['required', 'decimal:0,4', 'gt:0', 'max:24'],
            'annual_working_days' => ['required', 'decimal:0,4', 'gt:0', 'max:366'],
            'proration_method' => ['required', Rule::in(PayGroup::PRORATION_METHODS)],
            'rounding_mode' => ['required', Rule::in(PayGroup::ROUNDING_MODES)],
            'is_active' => ['boolean'],
        ];
    }
}
