<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class CancelAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('assessment'));
    }

    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:2000']];
    }
}
