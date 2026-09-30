<?php

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;

class ApproveLeaveApplicationRequest extends FormRequest
{
    /**
     * Requires leave.approval.approve and being the approver on the current step.
     */
    public function authorize(): bool
    {
        return $this->user()->can('approve', $this->route('leaveApplication'));
    }

    public function rules(): array
    {
        return [
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }
}
