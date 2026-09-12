<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeavePolicyRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('leave_policy_rule.update');
    }

    public function rules(): array
    {
        return [
            'leave_policy_id' => ['required', 'exists:leave_policies,id'],
            'leave_type_id' => [
                'required',
                'exists:leave_types,id',
                Rule::unique('leave_policy_rules')
                    ->where(fn($query) => $query->where('leave_policy_id', $this->leave_policy_id))
                    ->ignore($this->route('leavePolicyRule')),
            ],
            'accrual_method' => ['required', 'string', 'in:none,monthly,annual,fixed_grant'],
            'accrual_rate' => ['required', 'numeric', 'min:0'],
            'grant_frequency' => ['required', 'string', 'in:none,monthly,annual,quarterly'],
            'max_balance' => ['required', 'numeric', 'min:0'],
            'carry_forward_limit' => ['required', 'numeric', 'min:0'],
            'min_service_months' => ['required', 'integer', 'min:0'],
            'allow_negative' => ['sometimes', 'boolean'],
            'requires_approval' => ['sometimes', 'boolean'],
            'requires_attachment' => ['sometimes', 'boolean'],
            'allows_half_day' => ['sometimes', 'boolean'],
            'allows_hourly' => ['sometimes', 'boolean'],
            'expires' => ['sometimes', 'boolean'],
        ];
    }
}
