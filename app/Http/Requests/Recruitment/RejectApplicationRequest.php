<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RejectApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reject', $this->route('application'));
    }

    public function rules(): array
    {
        return [
            'rejection_reason_id' => ['required', 'integer', Rule::exists('rejection_reasons', 'id')->where('is_active', true)],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return ['rejection_reason_id' => 'rejection reason'];
    }
}
