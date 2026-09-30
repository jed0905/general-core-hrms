<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\EmployeeMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeMovementTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('employee_movement_type.create');
    }

    public function rules(): array
    {
        return [
            // Stable machine-readable identifier; can't be changed later.
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:employee_movement_types,code'],
            ...$this->sharedRules(),
        ];
    }

    protected function sharedRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:1000'],
            'affected_fields' => ['nullable', 'array'],
            'affected_fields.*' => ['distinct', Rule::in(EmployeeMovement::FIELDS)],
            'employee_status' => ['nullable', Rule::in(Employee::STATUSES)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Use lowercase letters, numbers and underscores, starting with a letter (e.g. lateral_transfer).',
        ];
    }
}
