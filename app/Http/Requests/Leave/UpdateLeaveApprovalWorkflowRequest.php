<?php

namespace App\Http\Requests\Leave;

use Illuminate\Validation\Rule;

class UpdateLeaveApprovalWorkflowRequest extends StoreLeaveApprovalWorkflowRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('leave_approval_workflow.update');
    }

    /**
     * Existing steps may only be referenced by the workflow that owns them.
     */
    protected function stepIdRules(): array
    {
        return [
            'nullable',
            'integer',
            Rule::exists('leave_approval_workflow_steps', 'id')
                ->where('leave_approval_workflow_id', $this->route('leaveApprovalWorkflow')->id),
        ];
    }
}
