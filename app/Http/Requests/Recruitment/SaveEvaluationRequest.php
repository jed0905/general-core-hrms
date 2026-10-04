<?php

namespace App\Http\Requests\Recruitment;

use App\Models\ApplicationEvaluation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Save or submit the current user's own scorecard for an interview. Whether
 * every criterion is rated (on submit) and whether the scorecard is still a
 * draft are checked in EvaluationService against the database.
 */
class SaveEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('evaluate', $this->route('interview'));
    }

    public function rules(): array
    {
        $submitting = $this->route()->getName() === 'recruitment.interviews.evaluation.submit';

        return [
            'recommendation' => [$submitting ? 'required' : 'nullable', Rule::in(ApplicationEvaluation::RECOMMENDATIONS)],
            'comments' => ['nullable', 'string', 'max:5000'],
            'scores' => [$submitting ? 'required' : 'nullable', 'array', 'max:50'],
            'scores.*.evaluation_criterion_id' => ['required', 'integer', 'distinct', Rule::exists('evaluation_criteria', 'id')],
            'scores.*.rating' => ['required', 'integer', 'between:1,5'],
            'scores.*.comments' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
