<?php

namespace App\Http\Requests\Payroll;

use App\Models\PayComponent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A pay component and its per-country tax/contribution treatments (validated against each localization in the service).
 */
class PayComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payroll.config.manage');
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9_]+$/', Rule::unique('pay_components', 'code')->ignore($this->route('payComponent'))],
            'name' => ['required', 'string', 'max:255'],
            'line_class' => ['required', Rule::in(PayComponent::LINE_CLASSES)],
            'category' => ['required', Rule::in(PayComponent::CATEGORIES)],
            'legal_entity_id' => ['nullable', 'exists:legal_entities,id'],
            'priority' => ['required', 'integer', 'between:1,999'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
            'treatments' => ['array'],
            'treatments.*.country_code' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
            'treatments.*.tax_treatment' => ['required', 'string', 'max:48'],
            'treatments.*.contribution_treatment' => ['required', 'string', 'max:48'],
        ];
    }
}
