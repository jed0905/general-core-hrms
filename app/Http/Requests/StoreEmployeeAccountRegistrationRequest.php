<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Traits\SanitizesInput;

class StoreEmployeeAccountRegistrationRequest extends FormRequest
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
            'employee_number' => [
                'required',
                'numeric',
                'digits:6',
                Rule::exists('employees', 'employee_number'),
            ],
            'username' => [
                'required',
                'string',
                'min:5',
                'max:255',
                'regex:/^[a-z0-9._-]+$/',
                Rule::unique('users', 'username'),
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::exists('personal_information', 'email'),
            ],
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string|min:8',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_number.numeric' => 'The employee ID must contain numbers only.',
            'employee_number.digits_between' => 'The employee ID must be between 1 and 20 digits.',
            'employee_number.exists' => 'The employee ID is not found or already has an account.',
            'username.unique' => 'This username is already taken.',
            'username.min' => 'Username must be at least 5 characters.',
            'username.max' => 'Username cannot exceed 255 characters.',
            'username.regex' => 'Username can only contain lowercase letters, numbers, dots, underscores, and hyphens.',
            'email.unique' => 'This email address is already registered.',
            'password.confirmed' => 'The password confirmation does not match.',
            'password.min' => 'The password must be at least 8 characters.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge($this->sanitizeData($this->only([
            'employee_number',
            'username',
            'email',
            'password',
            'password_confirmation'
        ]), [
            'employee_number' => 'numeric',
            'username' => 'username',
            'email' => 'email',
            'password' => 'password',
            'password_confirmation' => 'password'
        ]));
    }
}
