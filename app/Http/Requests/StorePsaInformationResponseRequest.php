<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePsaInformationResponseRequest extends FormRequest
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
            'operating_unit_id' => 'required|numeric|exists:operating_units,id',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable',
            'last_name' => 'required|string|max:255',
            'suffix' => 'nullable',
            'email' => 'required|email|unique:personal_information,email',
            'date_hired' => 'required|date',
            'birthdate' => 'required|date',
            'sex' => 'required|string',
            'marital_status' => 'required|string',
            'blood_type' => 'nullable|string',
            'mobile_number' => 'required|string|max:20',
            'barangay' => 'required|string|max:255',
            'municipality' => 'required|string|max:255',
            'province' => 'required|string|max:255',
            'postal_code' => 'required|string|max:10',
            'place_of_birth' => 'required|string|max:255',
        ];
    }
}
