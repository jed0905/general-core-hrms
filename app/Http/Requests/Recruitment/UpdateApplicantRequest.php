<?php

namespace App\Http\Requests\Recruitment;

use App\Models\Applicant;
use Illuminate\Validation\Rule;

class UpdateApplicantRequest extends ApplicantRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('applicant'));
    }

    protected function ignoreId(): ?int
    {
        return $this->route('applicant')?->id;
    }

    public function rules(): array
    {
        return parent::rules() + [
            'status' => ['required', Rule::in([Applicant::STATUS_ACTIVE, Applicant::STATUS_ARCHIVED])],
        ];
    }
}
