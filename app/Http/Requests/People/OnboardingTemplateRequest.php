<?php

namespace App\Http\Requests\People;

use App\Models\OnboardingTemplate;
use App\Models\OnboardingTemplateTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create or edit an onboarding template with its task list.
 */
class OnboardingTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $template = $this->route('onboardingTemplate');

        return $template ? $this->user()->can('update', $template) : $this->user()->can('create', OnboardingTemplate::class);
    }

    public function rules(): array
    {
        $row = 'tasks.*.';

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('onboarding_templates', 'name')->ignore($this->route('onboardingTemplate'))],
            'description' => ['nullable', 'string', 'max:5000'],
            'employment_status_id' => ['nullable', 'integer', Rule::exists('employment_statuses', 'id')],
            'is_active' => ['required', 'boolean'],
            'tasks' => ['required', 'array', 'min:1', 'max:100'],
            $row.'id' => ['nullable', 'integer'],
            $row.'title' => ['required', 'string', 'max:255'],
            $row.'description' => ['nullable', 'string', 'max:5000'],
            $row.'category' => ['required', Rule::in(OnboardingTemplateTask::CATEGORIES)],
            $row.'assignee_type' => ['required', Rule::in(OnboardingTemplateTask::ASSIGNEE_TYPES)],
            $row.'assignee_employee_id' => ['nullable', 'required_if:'.$row.'assignee_type,'.OnboardingTemplateTask::ASSIGNEE_SPECIFIC, 'integer', Rule::exists('employees', 'id')->whereNotIn('status', ['archived', 'terminated'])],
            $row.'due_relative_to' => ['required', Rule::in([OnboardingTemplateTask::RELATIVE_START, OnboardingTemplateTask::RELATIVE_CREATED])],
            $row.'due_offset_days' => ['required', 'integer', 'between:-365,365'],
            $row.'is_required' => ['required', 'boolean'],
            $row.'employee_visible' => ['required', 'boolean'],
            $row.'requires_verification' => ['required', 'boolean'],
            $row.'required_document_type_id' => ['nullable', 'integer', Rule::exists('employee_document_types', 'id')->where('is_active', true)],
            $row.'is_active' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'tasks.*.title' => 'task title',
            'tasks.*.assignee_employee_id' => 'designated employee',
            'tasks.*.due_offset_days' => 'due offset',
            'tasks.*.required_document_type_id' => 'required document type',
        ];
    }
}
