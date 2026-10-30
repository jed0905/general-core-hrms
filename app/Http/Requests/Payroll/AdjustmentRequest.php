<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A one-time earning or deduction for a period (pending until approved).
 */
class AdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payroll.input.manage');
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'pay_component_id' => ['required', 'exists:pay_components,id'],
            'amount' => ['required', 'decimal:0,4', 'not_in:0'],
            'currency' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'remarks' => ['required', 'string', 'max:255'],
            'corrects_run_employee_id' => ['nullable', 'exists:payroll_run_employees,id'],
        ];
    }
}
