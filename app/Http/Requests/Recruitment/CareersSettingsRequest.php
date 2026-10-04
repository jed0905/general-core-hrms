<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class CareersSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recruitment.config.manage');
    }

    public function rules(): array
    {
        return [
            'headline' => ['required', 'string', 'max:191'],
            'introduction' => ['nullable', 'string', 'max:5000'],
            'application_instructions' => ['nullable', 'string', 'max:5000'],
            'privacy_notice' => ['required', 'string', 'max:10000'],
        ];
    }
}
