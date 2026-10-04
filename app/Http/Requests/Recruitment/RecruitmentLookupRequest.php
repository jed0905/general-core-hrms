<?php

namespace App\Http\Requests\Recruitment;

use App\Models\AssessmentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create/update a recruitment lookup (source, rejection reason, interview type,
 * assessment type, evaluation criterion) or rename a stage. Fields a lookup
 * doesn't have are ignored by RecruitmentSettingsService.
 */
class RecruitmentLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recruitment.config.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65000'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'result_type' => ['sometimes', Rule::in(AssessmentType::RESULT_TYPES)],
        ];
    }
}
