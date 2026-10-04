<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Which result fields are required depends on the assessment's result type
 * (stored on the assessment), so AssessmentService checks that.
 */
class CompleteAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('assessment'));
    }

    public function rules(): array
    {
        return [
            'score' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'maximum_score' => ['nullable', 'required_with:score', 'numeric', 'gt:0', 'max:999999.99'],
            'passed' => ['nullable', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
