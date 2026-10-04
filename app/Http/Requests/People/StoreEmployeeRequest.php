<?php

namespace App\Http\Requests\People;

use App\Models\Employee;
use App\Models\NumberSequence;
use App\Services\NumberSequenceService;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends EmployeeRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Employee::class);
    }

    /**
     * Optional while automatic numbering is on (EmployeeService generates it); required otherwise.
     */
    protected function employeeNumberRules(): array
    {
        $required = ! app(NumberSequenceService::class)->autoGenerates(NumberSequence::EMPLOYEE_NUMBER);

        return [Rule::requiredIf($required), 'nullable', 'string', 'max:255', Rule::unique('employees', 'employee_number')];
    }
}
