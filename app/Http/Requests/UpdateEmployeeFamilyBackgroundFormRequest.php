<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeFamilyBackgroundFormRequest extends FormRequest
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
            // Spouse Information
            'spouse_lastname' => 'nullable|string|max:50',
            'spouse_firstname' => 'nullable|string|max:50',
            'spouse_middlename' => 'nullable|string|max:50',
            'spouse_suffix' => 'nullable|string|max:10',
            'occupation' => 'nullable|string|max:100',
            'employer_business_name' => 'nullable|string|max:100',
            'business_address' => 'nullable|string|max:255',
            'telephone_no' => 'nullable|string|max:15',

            // Children Information
            'children' => 'nullable|array',

            // Family Background Information
            'father_lastname' => 'nullable|string|max:50',
            'father_firstname' => 'nullable|string|max:50',
            'father_middlename' => 'nullable|string|max:50',
            'father_suffix' => 'nullable|string|max:10',
            'mother_lastname' => 'nullable|string|max:50',
            'mother_firstname' => 'nullable|string|max:50',
            'mother_middlename' => 'nullable|string|max:50',
        ];
    }
}
