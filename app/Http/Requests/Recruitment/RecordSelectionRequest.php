<?php

namespace App\Http\Requests\Recruitment;

use App\Models\ApplicationSelection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A selection decision. Eligibility, evaluation completeness and openings are
 * checked in SelectionService against the locked rows.
 */
class RecordSelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [ApplicationSelection::class, $this->route('application')]);
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(ApplicationSelection::DECISIONS)],
            'remarks' => ['nullable', 'required_if:decision,'.ApplicationSelection::NOT_SELECTED, 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['remarks.required_if' => 'Say why the candidate was not selected.'];
    }
}
