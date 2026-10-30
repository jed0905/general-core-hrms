<?php

namespace App\Http\Requests\Payroll;

use App\Models\EmployeeCompensation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A salary structure with decimal ranges per existing pay grade.
 */
class SalaryStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payroll.config.manage');
    }

    public function rules(): array
    {
        return [
            'legal_entity_id' => ['nullable', 'exists:legal_entities,id'],
            'name' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'basis' => ['required', Rule::in(EmployeeCompensation::BASES)],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            'is_active' => ['boolean'],
            'grades' => ['array'],
            'grades.*.pay_grade_id' => ['required', 'distinct', 'exists:pay_grades,id'],
            'grades.*.minimum_amount' => ['required', 'decimal:0,4', 'min:0', 'max:999999999999999'],
            'grades.*.midpoint_amount' => ['nullable', 'decimal:0,4', 'min:0'],
            'grades.*.maximum_amount' => ['required', 'decimal:0,4', 'min:0', 'max:999999999999999'],
        ];
    }
}
