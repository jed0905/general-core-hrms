<?php

namespace App\Http\Requests\Payroll;

use App\Models\EmployeePayComponent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A recurring allowance or deduction on an assignment.
 */
class EmployeePayComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('compensation.create');
    }

    public function rules(): array
    {
        return [
            'pay_component_id' => ['required', Rule::exists('pay_components', 'id')->where('is_system', false)->where('is_active', true)],
            'amount' => ['required', 'decimal:0,4', 'min:0', 'max:999999999999999'],
            'currency' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'amount_basis' => ['required', Rule::in(EmployeePayComponent::BASES)],
            'total_limit' => ['nullable', 'decimal:0,4', 'gt:0'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ];
    }
}
