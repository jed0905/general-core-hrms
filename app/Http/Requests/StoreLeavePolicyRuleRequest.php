<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeavePolicyRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('leave_policy_rule.create');
    }

    public function rules(): array
    {
        return [
            'leave_policy_id' => ['required', 'exists:leave_policies,id'],
            'leave_type_id' => [
                'required',
                'exists:leave_types,id',
                Rule::unique('leave_policy_rules')->where(
                    fn ($query) => $query->where('leave_policy_id', $this->leave_policy_id)
                ),
            ],
            'accrual_method' => ['required', 'string', 'in:fixed,monthly,annually,per_payroll,none'],
            'accrual_rate' => ['nullable', 'numeric', 'min:0'],
            'grant_frequency' => ['required', 'string', 'in:monthly,quarterly,annually,per_payroll,none'],
            'maximum_balance' => ['nullable', 'numeric', 'min:0'],
            'carry_forward_limit' => ['nullable', 'numeric', 'min:0'],
            'minimum_service_months' => ['required', 'integer', 'min:0'],
            'waiting_period' => ['nullable', 'integer', 'min:0'],
            'allow_negative' => ['sometimes', 'boolean'],
            'requires_approval' => ['sometimes', 'boolean'],
            'requires_attachment' => ['sometimes', 'boolean'],
            'allows_half_day' => ['sometimes', 'boolean'],
            'allows_hourly' => ['sometimes', 'boolean'],
            'expires' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
