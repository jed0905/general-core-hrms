<?php

namespace App\Http\Requests\Recruitment;

use App\Models\Vacancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class VacancyRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('salary_currency')) {
            $this->merge(['salary_currency' => strtoupper($this->input('salary_currency'))]);
        }
    }

    /**
     * Full field rules; position fields are only required when no requisition supplies them.
     */
    protected function fullRules(bool $fromRequisition): array
    {
        $position = $fromRequisition ? ['nullable'] : ['required'];

        return [
            'title' => ['nullable', 'string', 'max:255'],
            'job_title_id' => [...$position, 'integer', 'exists:job_titles,id'],
            'department_id' => [...$position, 'integer', 'exists:departments,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'employment_status_id' => ['nullable', 'integer', 'exists:employment_statuses,id'],
            'openings' => ['required', 'integer', 'min:1', 'max:999'],
            ...$this->contentRules(),
            'salary_min' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'salary_max' => ['nullable', 'numeric', 'min:0', 'max:9999999999', 'gte:salary_min'],
            'salary_currency' => ['nullable', 'string', 'size:3', 'alpha'],
            'opening_date' => ['nullable', 'date'],
            'closing_date' => ['nullable', 'date', 'after_or_equal:opening_date'],
            'visibility' => ['required', Rule::in(Vacancy::VISIBILITIES)],
            'hiring_manager_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->whereNotIn('status', ['archived', 'terminated'])],
        ];
    }

    protected function contentRules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:20000'],
            'responsibilities' => ['nullable', 'string', 'max:20000'],
            'qualifications' => ['nullable', 'string', 'max:20000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'job_title_id' => 'job title',
            'department_id' => 'department',
            'hiring_manager_id' => 'hiring manager',
            'salary_max' => 'maximum salary',
        ];
    }
}
