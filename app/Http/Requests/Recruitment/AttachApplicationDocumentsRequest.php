<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachApplicationDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('attachDocuments', $this->route('application'));
    }

    public function rules(): array
    {
        return [
            'document_ids' => ['required', 'array', 'min:1', 'max:20'],
            'document_ids.*' => ['integer', Rule::exists('applicant_documents', 'id')->where('applicant_id', $this->route('application')->applicant_id)],
        ];
    }

    public function messages(): array
    {
        return ['document_ids.*.exists' => "Only the applicant's own documents can be attached."];
    }
}
