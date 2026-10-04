<?php

namespace App\Http\Requests\Recruitment;

use App\Models\Applicant;

class StoreApplicantRequest extends ApplicantRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Applicant::class);
    }

    public function rules(): array
    {
        return parent::rules() + [
            // Processing a candidate's data requires their consent (data privacy).
            'privacy_consent' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + ['privacy_consent.accepted' => "The applicant's privacy consent is required."];
    }
}
