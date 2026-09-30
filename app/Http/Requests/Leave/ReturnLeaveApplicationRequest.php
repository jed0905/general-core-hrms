<?php

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;

class ReturnLeaveApplicationRequest extends FormRequest
{
    /**
     * Requires leave.approval.return and being the approver on the current step.
     */
    public function authorize(): bool
    {
        return $this->user()->can('return', $this->route('leaveApplication'));
    }

    public function rules(): array
    {
        return [
            'remarks' => ['required', 'string', 'max:500'],
        ];
    }
}
