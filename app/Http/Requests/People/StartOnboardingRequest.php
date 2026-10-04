<?php

namespace App\Http\Requests\People;

use App\Models\Onboarding;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Start onboarding for an existing employee. Eligibility (no active case,
 * re-onboarding confirmation, active template) is checked in OnboardingService
 * with the employee row locked.
 */
class StartOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Onboarding::class);
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'onboarding_template_id' => ['required', 'integer', Rule::exists('onboarding_templates', 'id')->where('is_active', true)],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'target_completion_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'application_conversion_id' => ['nullable', 'integer', Rule::exists('application_conversions', 'id')],
            'confirm_reonboarding' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['employee_id' => 'employee', 'onboarding_template_id' => 'template'];
    }
}
