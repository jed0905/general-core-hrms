<?php

namespace App\Http\Requests\Careers;

use Illuminate\Foundation\Http\FormRequest;

class PublicApplicationDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['applicant_document_type_id' => PublicDocumentRules::type(), 'file' => PublicDocumentRules::file()];
    }
}
