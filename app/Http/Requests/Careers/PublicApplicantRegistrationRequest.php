<?php

namespace App\Http\Requests\Careers;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Only what an applicant record needs, plus explicit privacy consent. No
 * password here: it is chosen after the emailed link proves the address.
 */
class PublicApplicantRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'email' => ['required', 'email:rfc', 'max:191'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^\+?[0-9(][0-9\s\-().]{6,24}$/'],
            'address' => ['nullable', 'string', 'max:1000'],
            'privacy_consent' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return ['privacy_consent.accepted' => 'Please agree to the privacy notice to continue.'];
    }
}
