<?php

namespace App\Http\Requests\Recruitment;

class UpdateJobRequisitionRequest extends JobRequisitionRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('requisition'));
    }
}
