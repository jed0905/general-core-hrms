<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmploymentStatusFormRequest extends FormRequest
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
            'employee_id' => ['required', 'exists:employees,id'],
            'employee_number' => ['required', 'string', 'max:255'],
            'effective_date' => ['required', 'date'],
            'movement_type' => ['required', 'in:retirement,resignation,termination,end_of_contract,transfer'],
            'new_operating_unit_id' => ['required_if:movement_type,transfer', 'nullable', 'exists:operating_units,id'],
            'reason' => ['required_if:movement_type,termination', 'nullable', 'string'],
            'transfer_position' => ['required_if:movement_type,transfer', 'boolean'],
        ];
    }
}
