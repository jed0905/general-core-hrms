<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeePersonalInformationFormRequest extends FormRequest
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
            'firstname' => ['required', 'string', 'max:255'],
            'middlename' => ['nullable', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'suffix' => ['nullable', 'string', 'max:50'],
            'sex' => ['nullable', 'string'],
            'civil_status' => ['nullable', 'string'],
            'otherCivilStatus' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'place_of_birth' => ['nullable', 'string', 'max:255'],
            'height' => ['nullable', 'numeric'],
            'weight' => ['nullable', 'numeric'],
            'blood_type' => ['nullable', 'string'],
            'citizenship' => ['nullable', 'string'],
            'dual_citizenship_type' => ['nullable', 'string'],
            'dual_citizenship_country' => ['nullable', 'string'],

            'residential_province' => ['nullable', 'string'],
            'residential_city_municipality' => ['nullable', 'string'],
            'residential_barangay' => ['nullable', 'string'],
            'residential_subdivision' => ['nullable', 'string'],
            'residential_street' => ['nullable', 'string'],
            'residential_house_no' => ['nullable', 'string'],
            'residential_zip_code' => ['nullable', 'string'],

            'permanent_province' => ['nullable', 'string'],
            'permanent_city_municipality' => ['nullable', 'string'],
            'permanent_barangay' => ['nullable', 'string'],
            'permanent_subdivision' => ['nullable', 'string'],
            'permanent_street' => ['nullable', 'string'],
            'permanent_house_no' => ['nullable', 'string'],
            'permanent_zip_code' => ['nullable', 'string'],

            'telephone_no' => ['nullable', 'numeric'],
            'mobile_no' => ['nullable', 'numeric'],
            'email' => ['nullable', 'email', 'max:255'],
            'gsis_id_no' => ['nullable', 'numeric'],
            'pag_ibig_id_no' => ['nullable', 'numeric'],
            'philhealth_id_no' => ['nullable', 'numeric'],
            'sss_id_no' => ['nullable', 'numeric'],
            'tin_id_no' => ['nullable', 'numeric'],
            'agencyIdNo' => ['nullable', 'numeric'],

            'signature' => ['nullable', 'file', 'mimes:png', 'max:5120'], // max size of 5MB

        ];
    }
}
