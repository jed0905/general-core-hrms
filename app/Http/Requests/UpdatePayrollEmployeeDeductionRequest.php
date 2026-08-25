<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePayrollEmployeeDeductionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_number' => 'required|exists:employees,id',
            'deduction_id' => 'required|exists:payroll_deductions,id',
            'amount' => 'required|numeric',
            'date_start' => 'required|date',
            'date_end' => 'required|date',
            'deduction_period' => 'required|string',
        ];
    }
}
