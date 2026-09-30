<?php

namespace App\Http\Requests\Leave;

class UpdateLeaveApplicationRequest extends StoreLeaveApplicationRequest
{
    /**
     * Ownership, the update_own/update capability and the editable state are checked by the policy.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('leaveApplication'));
    }
}
