<?php

namespace App\Http\Requests\People;

use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends EmployeeRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('employee'));
    }

    protected function ignoreId(): ?int
    {
        return $this->route('employee')?->id;
    }

    protected function employeeNumberRules(): array
    {
        return ['required', 'string', 'max:255', Rule::unique('employees', 'employee_number')->ignore($this->ignoreId())];
    }
}
