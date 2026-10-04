<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApplicantDocumentRequest extends FormRequest
{
    public const MIMES = ['pdf', 'doc', 'docx', 'odt', 'rtf', 'txt', 'jpg', 'jpeg', 'png'];

    public const MAX_KB = 10240;

    public function authorize(): bool
    {
        return $this->user()->can('manageDocuments', $this->route('applicant'));
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:'.implode(',', self::MIMES), 'max:'.self::MAX_KB],
            'applicant_document_type_id' => ['required', 'integer', Rule::exists('applicant_document_types', 'id')->where('is_active', true)],
            'description' => ['nullable', 'string', 'max:1000'],
            // Optionally submit the new file with one of this applicant's applications right away.
            'application_id' => ['nullable', 'integer', Rule::exists('applications', 'id')->where('applicant_id', $this->route('applicant')?->id)],
        ];
    }

    public function attributes(): array
    {
        return ['applicant_document_type_id' => 'document type'];
    }
}
