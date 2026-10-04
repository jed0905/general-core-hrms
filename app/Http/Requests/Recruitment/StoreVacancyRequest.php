<?php

namespace App\Http\Requests\Recruitment;

use App\Models\JobRequisition;
use App\Models\Vacancy;

class StoreVacancyRequest extends VacancyRequest
{
    public function authorize(): bool
    {
        if (! $this->user()->can('create', Vacancy::class)) {
            return false;
        }

        $requisition = $this->filled('job_requisition_id') ? JobRequisition::find($this->input('job_requisition_id')) : null;

        return ! $requisition || $this->user()->can('view', $requisition);
    }

    public function rules(): array
    {
        return ['job_requisition_id' => ['nullable', 'integer', 'exists:job_requisitions,id']]
            + $this->fullRules($this->filled('job_requisition_id'));
    }
}
