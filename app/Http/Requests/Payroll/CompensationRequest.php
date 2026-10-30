<?php

namespace App\Http\Requests\Payroll;

use App\Models\EmployeeCompensation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A new salary record (pending approval). Precision is checked against the currency in the service.
 */
class CompensationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('compensation.create');
    }

    public function rules(): array
    {
        return [
            'basis' => ['required', Rule::in(EmployeeCompensation::BASES)],
            'amount' => ['required', 'decimal:0,4', 'min:0', 'max:999999999999999'],
            'currency' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'pay_grade_id' => ['nullable', 'exists:pay_grades,id'],
            'salary_structure_id' => ['nullable', 'exists:salary_structures,id'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
