<?php

namespace App\Http\Requests\Recruitment;

use App\Models\JobRequisition;

class StoreJobRequisitionRequest extends JobRequisitionRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', JobRequisition::class);
    }
}
