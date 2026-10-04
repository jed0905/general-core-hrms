<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class PublishVacancyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('publish', $this->route('vacancy'));
    }

    public function rules(): array
    {
        return ['show_salary_publicly' => ['sometimes', 'boolean']];
    }
}
