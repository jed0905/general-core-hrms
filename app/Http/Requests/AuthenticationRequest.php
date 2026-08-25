<?php

namespace App\Http\Requests;

use App\Traits\SanitizesInput;
use Illuminate\Foundation\Http\FormRequest;

class AuthenticationRequest extends FormRequest
{
    use SanitizesInput;
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => 'required|string|max:50',
            'password' => 'required|string|max:255',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $sanitizedData = $this->sanitizeData($this->all(), [
            'username' => 'username',
            'password' => 'password',
        ]);

        $this->replace($sanitizedData);
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'username.required' => 'Username is required.',
            'username.string' => 'Username must be a string.',
            'username.max' => 'Username must not exceed 50 characters.',
            'password.required' => 'Password is required.',
            'password.string' => 'Password must be a string.',
            'password.max' => 'Password must not exceed 255 characters.',
        ];
    }
}
