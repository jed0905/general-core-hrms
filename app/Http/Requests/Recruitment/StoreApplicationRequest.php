<?php

namespace App\Http\Requests\Recruitment;

use App\Models\Application;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Application::class);
    }

    public function rules(): array
    {
        return [
            'applicant_id' => ['required', 'integer', 'exists:applicants,id'],
            'vacancy_id' => ['required', 'integer', 'exists:vacancies,id'],
            'recruitment_source_id' => ['nullable', 'integer', Rule::exists('recruitment_sources', 'id')->where('is_active', true)],
            'document_ids' => ['nullable', 'array', 'max:20'],
            'document_ids.*' => ['integer', Rule::exists('applicant_documents', 'id')->where('applicant_id', $this->input('applicant_id'))],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['document_ids.*.exists' => "Only the applicant's own documents can be attached."];
    }
}
