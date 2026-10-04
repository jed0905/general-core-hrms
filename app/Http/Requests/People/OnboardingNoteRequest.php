<?php

namespace App\Http\Requests\People;

use Illuminate\Foundation\Http\FormRequest;

class OnboardingNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('onboarding'));
    }

    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:5000']];
    }
}
