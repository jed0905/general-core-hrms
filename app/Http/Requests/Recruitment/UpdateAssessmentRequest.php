<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('assessment'));
    }

    public function rules(): array
    {
        return StoreAssessmentRequest::detailRules();
    }
}
