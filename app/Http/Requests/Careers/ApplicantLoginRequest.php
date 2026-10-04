<?php

namespace App\Http\Requests\Careers;

use Illuminate\Foundation\Http\FormRequest;

class ApplicantLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:191'],
            'password' => ['required', 'string', 'max:200'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }
}
