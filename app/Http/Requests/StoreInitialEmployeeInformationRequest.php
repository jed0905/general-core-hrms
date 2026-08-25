<?php

namespace App\Http\Requests;

use App\Models\PersonalInformation;
use Illuminate\Foundation\Http\FormRequest;

class StoreInitialEmployeeInformationRequest extends FormRequest
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
            'firstName' => 'required|string|max:255',
            'middleName' => 'nullable|string|max:255',
            'lastName' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:255',
            'photo' => 'nullable',
            'email' => 'required|email|unique:personal_information,email',
            'birthdate' => 'required|date',
            'date_hired' => 'required|date',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * This prevents creating a duplicate employee with the same
     * first name, middle name, last name and birthdate.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $firstName = $this->input('firstName');
            $middleName = $this->input('middleName');
            $lastName = $this->input('lastName');
            $birthdate = $this->input('birthdate');

            // If key fields are missing, skip duplicate check
            if (!$firstName || !$lastName || !$birthdate) {
                return;
            }

            $query = PersonalInformation::query()
                ->whereRaw('LOWER(firstname) = ?', [mb_strtolower($firstName)])
                ->whereRaw('LOWER(lastname) = ?', [mb_strtolower($lastName)])
                ->where('date_of_birth', $birthdate);

            // Middle name can be nullable, so only use it when provided
            if ($middleName !== null && $middleName !== '') {
                $query->whereRaw('LOWER(middlename) = ?', [mb_strtolower($middleName)]);
            }

            if ($query->exists()) {
                $validator->errors()->add(
                    'birthdate',
                    'An employee with the same name and birthdate already exists in the system.'
                );
            }
        });
    }
}
