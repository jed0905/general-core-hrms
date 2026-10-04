<?php

namespace App\Http\Requests\People;

use App\Models\EmployeeDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeDocumentRequest extends FormRequest
{
    /** Allowed upload types and size (KB). */
    public const MIMES = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'odt', 'ods', 'txt'];

    public const MAX_KB = 10240;

    public function authorize(): bool
    {
        return $this->user()->can('create', [EmployeeDocument::class, $this->route('employee')]);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:'.implode(',', self::MIMES), 'max:'.self::MAX_KB],
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
