<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Descriptive fields only. Employment data is corrected by cancelling and
 * re-recording the movement, so history is never silently rewritten.
 */
class UpdateEmployeeMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('employeeMovement'));
    }

    public function rules(): array
    {
        return [
            'reference_number' => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
