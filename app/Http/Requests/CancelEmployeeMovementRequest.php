<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelEmployeeMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cancel', $this->route('employeeMovement'));
    }

    public function rules(): array
    {
        return [
            'cancellation_reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
