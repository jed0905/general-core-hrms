<?php

namespace App\Http\Requests\Careers;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Applying online. Only documents and a short note: the applicant, the
 * vacancy (route slug) and everything else are decided server-side, so fields
 * like status, stage or employee_id can't be submitted.
 */
class PublicApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // AuthenticateApplicant + ownership in CareersApplicationService
    }

    public function rules(): array
    {
        return [
            'uploads' => ['nullable', 'array', 'max:'.PublicDocumentRules::MAX_FILES],
            'uploads.*.applicant_document_type_id' => PublicDocumentRules::type(),
            'uploads.*.file' => PublicDocumentRules::file(),
            'document_ids' => ['nullable', 'array', 'max:'.PublicDocumentRules::MAX_FILES],
            'document_ids.*' => ['integer', 'distinct'],
            'cover_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return ['uploads.*.file' => 'file', 'uploads.*.applicant_document_type_id' => 'document type'];
    }
}
