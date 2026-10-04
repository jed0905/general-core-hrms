<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Cancel (optional reason) or complete (optional remarks) a scheduled interview.
 */
class CloseInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('interview'));
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:2000'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
