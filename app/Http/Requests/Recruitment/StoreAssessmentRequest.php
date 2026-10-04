<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('createAssessment', $this->route('application'));
    }

    public function rules(): array
    {
        return [
            'assessment_type_id' => ['required', 'integer', Rule::exists('assessment_types', 'id')->where('is_active', true)],
        ] + self::detailRules();
    }

    public static function detailRules(): array
    {
        return [
            'scheduled_at' => ['nullable', 'date'],
            'assessor_employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->whereNotIn('status', ['archived', 'terminated'])],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
