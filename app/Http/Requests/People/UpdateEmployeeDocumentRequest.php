<?php

namespace App\Http\Requests\People;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('document'));
    }

    public function rules(): array
    {
        return [
            'employee_document_type_id' => ['required', Rule::exists('employee_document_types', 'id')->where('is_active', true)],
            'description' => ['nullable', 'string', 'max:2000'],
            'expires_on' => ['nullable', 'date'],
        ];
    }

    public function attributes(): array
    {
        return ['employee_document_type_id' => 'document type', 'expires_on' => 'expiry date'];
    }
}
