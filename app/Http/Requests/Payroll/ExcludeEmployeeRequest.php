<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Exclude an employee assignment from a run, with a reason.
 */
class ExcludeEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageEmployees', $this->route('payrollRun'));
    }

    public function rules(): array
    {
        return [
            'employment_assignment_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
