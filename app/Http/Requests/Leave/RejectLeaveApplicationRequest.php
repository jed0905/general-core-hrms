<?php

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;

class RejectLeaveApplicationRequest extends FormRequest
{
    /**
     * Requires leave.approval.reject and being the approver on the current step.
     */
    public function authorize(): bool
    {
        return $this->user()->can('reject', $this->route('leaveApplication'));
    }

    public function rules(): array
    {
        return [
            'remarks' => ['required', 'string', 'max:500'],
        ];
    }
}
