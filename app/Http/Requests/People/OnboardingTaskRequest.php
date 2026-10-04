<?php

namespace App\Http\Requests\People;

use App\Models\OnboardingTemplateTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An extra task added by HR to one active onboarding.
 */
class OnboardingTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('onboarding'));
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['required', Rule::in(OnboardingTemplateTask::CATEGORIES)],
            'assignee_type' => ['required', Rule::in(OnboardingTemplateTask::ASSIGNEE_TYPES)],
            'assignee_employee_id' => ['nullable', 'required_if:assignee_type,'.OnboardingTemplateTask::ASSIGNEE_SPECIFIC, 'integer', Rule::exists('employees', 'id')->whereNotIn('status', ['archived', 'terminated'])],
            'due_date' => ['required', 'date_format:Y-m-d'],
            'is_required' => ['required', 'boolean'],
            'employee_visible' => ['required', 'boolean'],
            'requires_verification' => ['required', 'boolean'],
            'required_document_type_id' => ['nullable', 'integer', Rule::exists('employee_document_types', 'id')->where('is_active', true)],
        ];
    }
}
