<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\EmployeeMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Only the requested changes are accepted. The employee's current ("from")
 * state is read by the service from the locked employee row, so any from_*
 * values in the payload are ignored.
 */
class StoreEmployeeMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()->can('employee_movement.create')) {
            return false;
        }

        $employee = Employee::find($this->input('employee_id'));

        // Unknown employee: let validation report it. Known: record-level check.
        return $employee === null || $this->user()->can('create', [EmployeeMovement::class, $employee]);
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'movement_type_id' => ['required', 'integer', Rule::exists('employee_movement_types', 'id')->where('is_active', true)],
            'effective_date' => ['required', 'date_format:Y-m-d'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'changed_fields' => ['nullable', 'array'],
            'changed_fields.*' => ['distinct', Rule::in(EmployeeMovement::FIELDS)],
            'to_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'to_job_title_id' => ['nullable', 'integer', 'exists:job_titles,id'],
            'to_employment_status_id' => ['nullable', 'integer', 'exists:employment_statuses,id'],
            'to_location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'to_supervisor_id' => ['nullable', 'integer', 'exists:employees,id', 'different:employee_id'],
        ];
    }

    public function messages(): array
    {
        return [
            'movement_type_id.exists' => 'This movement type is not available.',
            'to_supervisor_id.different' => 'An employee cannot be their own supervisor.',
        ];
    }
}
