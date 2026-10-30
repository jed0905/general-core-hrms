<?php

namespace App\Http\Requests\Payroll;

use App\Models\PayrollRun;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create a regular or supplemental run for an open period.
 */
class StorePayrollRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PayrollRun::class);
    }

    public function rules(): array
    {
        return [
            'payroll_period_id' => ['required', 'exists:payroll_periods,id'],
            'run_type' => ['required', Rule::in([PayrollRun::TYPE_REGULAR, PayrollRun::TYPE_SUPPLEMENTAL])],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
