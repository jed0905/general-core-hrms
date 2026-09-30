<?php

namespace App\Http\Requests\Leave;

use App\Models\LeaveApprovalWorkflowStep;
use App\Services\Leave\LeaveApprovalWorkflowService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveApprovalWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('leave_approval_workflow.create');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:191'],
            'leave_policy_id' => ['nullable', 'exists:leave_policies,id'],
            'is_active' => ['sometimes', 'boolean'],
            'steps' => ['required', 'array', 'min:1', 'max:'.LeaveApprovalWorkflowService::MAX_STEPS],
            'steps.*.id' => $this->stepIdRules(),
            'steps.*.approver_type' => ['required', Rule::in(LeaveApprovalWorkflowStep::SUPPORTED_TYPES)],
            'steps.*.approver_employee_id' => [
                'nullable',
                'required_if:steps.*.approver_type,specific_employee',
                'exists:employees,id',
            ],
            'steps.*.is_required' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'steps.*.approver_type.in' => 'Supported approver types are: immediate supervisor, higher supervisor and specific employee.',
            'steps.*.approver_employee_id.required_if' => 'Choose the employee who approves this step.',
        ];
    }

    /**
     * New workflows have no steps yet.
     */
    protected function stepIdRules(): array
    {
        return ['prohibited'];
    }
}
