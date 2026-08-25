<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeCivilServiceEligibilityFormRequest extends FormRequest
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
            'eligibilities' => [
                'eligibility' => 'nullable',
                'rating' => 'nullable',
                'date_of_examination' => 'nullable',
                'place_of_examination' => 'nullable',
                'license_no' => 'nullable',
                'date_of_validity' => 'nullable',
            ],
        ];
    }
}
