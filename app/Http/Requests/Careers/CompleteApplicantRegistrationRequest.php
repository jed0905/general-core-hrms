<?php

namespace App\Http\Requests\Careers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CompleteApplicantRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the route requires a valid signed link
    }

    public function rules(): array
    {
        return ['password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()]];
    }
}
