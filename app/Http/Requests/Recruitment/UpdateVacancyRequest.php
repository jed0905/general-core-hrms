<?php

namespace App\Http\Requests\Recruitment;

/**
 * Users with recruitment.vacancy.update edit everything; an assigned hiring
 * manager without it edits the content fields only.
 */
class UpdateVacancyRequest extends VacancyRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('vacancy'));
    }

    public function contentOnly(): bool
    {
        return ! $this->user()->can('recruitment.vacancy.update');
    }

    public function rules(): array
    {
        return $this->contentOnly()
            ? $this->contentRules()
            : $this->fullRules((bool) $this->route('vacancy')->job_requisition_id);
    }
}
