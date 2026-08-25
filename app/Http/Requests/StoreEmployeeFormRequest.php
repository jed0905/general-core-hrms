<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeFormRequest extends FormRequest
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
        // dd($this->request);
        return [
            // Personal Info
            'firstName' => ['required', 'string'],
            'middleName' => ['nullable', 'string'],
            'lastName' => ['required', 'string'],
            'suffix' => ['nullable', 'string'],
            'sex' => ['required'],
            'civilStatus' => ['required'],
            'dateOfBirth' => ['required', 'date'],
            'placeOfBirth' => ['required', 'string'],
            'citizenship' => ['required', 'string'],

            // Residential Address
            'residentialHouseNo' => ['nullable', 'numeric'],
            'residentialStreet' => ['nullable', 'string'],
            'residentialSubdivision' => ['nullable', 'string'],
            'residentialProvince' => ['required', 'string'],
            'residentialMunicipality' => ['required', 'string'],
            'residentialBarangay' => ['required', 'string'],
            'residentialZipCode' => ['nullable', 'numeric'],

            // Permanent Address
            'permanentHouseNo' => ['nullable', 'numeric'],
            'permanentStreet' => ['nullable', 'string'],
            'permanentSubdivision' => ['nullable', 'string'],
            'permanentProvince' => ['required', 'string'],
            'permanentMunicipality' => ['required', 'string'],
            'permanentBarangay' => ['required', 'string'],
            'permanentZipCode' => ['nullable', 'numeric'],

            // Contact Info
            'telephoneNumber' => ['nullable'],
            'mobileNumber' => ['required', 'digits:11'],
            'emailAddress' => ['required', 'email'],

            // Employment Info
            // 'operatingUnit' => ['required'], // Optionalize in controller if needed
            'department' => ['required'],
            'position' => ['required'],
            'employeeType' => ['required'],
            'jobStatus' => ['required'],
            // 'shift' => ['required'], // Uncomment if used
            'appointmentDate' => ['required', 'date'],
            'salaryGradeId' => ['required'],
            'salaryStepId' => ['required'],

        ];
    }
}
