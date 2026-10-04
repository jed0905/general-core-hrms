<?php

namespace App\Http\Requests\Recruitment;

use App\Models\ApplicationScreening;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordScreeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('screen', $this->route('application'));
    }

    public function rules(): array
    {
        return [
            'result' => ['required', Rule::in(ApplicationScreening::RESULTS)],
            'screened_on' => ['required', 'date', 'before_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'rejection_reason_id' => ['nullable', 'required_if:result,'.ApplicationScreening::RESULT_FAILED, 'integer', Rule::exists('rejection_reasons', 'id')->where('is_active', true)],
        ];
    }

    public function messages(): array
    {
        return ['rejection_reason_id.required_if' => 'Choose why the application failed screening.'];
    }
}
