<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A time quantity for an employee in a period (pending until approved).
 */
class TimeInputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payroll.input.manage');
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'category' => ['required', 'string', 'max:48'],
            'quantity' => ['required', 'decimal:0,4', 'min:0', 'max:9999'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ];
    }
}
