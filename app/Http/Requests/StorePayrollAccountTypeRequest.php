<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePayrollAccountTypeRequest extends FormRequest
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
            'name' => 'required|string|max:255|unique:payroll_account_types,name',
            'operating_unit_id' => 'nullable|exists:operating_units,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Account type name is required.',
            'name.max' => 'Account type name cannot exceed 255 characters.',
            'name.unique' => 'Account type name already exists.',
            'operating_unit_id.exists' => 'Operating unit not found.',
        ];
    }
}
