<?php

namespace App\Http\Requests\People;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Complete a task. A document-required task needs an upload (or, for HR, an
 * existing document of that type); OnboardingTaskService checks which.
 */
class CompleteOnboardingTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('act', $this->route('onboardingTask'));
    }

    public function rules(): array
    {
        return [
            'remarks' => ['nullable', 'string', 'max:2000'],
            'file' => ['nullable', 'file', 'mimes:'.implode(',', StoreEmployeeDocumentRequest::MIMES), 'max:'.StoreEmployeeDocumentRequest::MAX_KB],
            'employee_document_id' => ['nullable', 'integer'],
        ];
    }
}
