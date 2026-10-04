<?php

namespace App\Http\Requests\People;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A required reason: cancel an onboarding, or skip an optional task.
 */
class OnboardingReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('onboardingTask')
            ? $this->user()->can('skip', $this->route('onboardingTask'))
            : $this->user()->can('cancel', $this->route('onboarding'));
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:2000']];
    }
}
