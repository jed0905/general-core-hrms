<?php

namespace App\Http\Requests;

class UpdateEmployeeMovementTypeRequest extends StoreEmployeeMovementTypeRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('employee_movement_type.update');
    }

    public function rules(): array
    {
        return [
            'code' => ['prohibited'],
            ...$this->sharedRules(),
        ];
    }
}
