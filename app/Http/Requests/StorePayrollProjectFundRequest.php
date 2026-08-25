<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePayrollProjectFundRequest extends FormRequest
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
            'name' => 'required',
            'allocation' => 'nullable',
            'employee_status' => 'required|exists:job_statuses,id',
            'employee_type' => 'required',
            'operating_unit_id' => 'nullable|exists:operating_units,id',
        ];
    }

    public function messages(): array
    {
        return [
            'operating_unit_id.exists' => 'Operating unit not found.',
            'employee_status.exists' => 'Employee status not found.',
        ];
    }
}
