<?php

namespace App\Http\Requests;

use App\Traits\SanitizesInput;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserFormRequest extends FormRequest
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
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => $this->cleanUsername($this->username),
            'password' => $this->cleanPassword($this->password),
            'password_confirmation' => $this->cleanPassword($this->password_confirmation),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'userRole' => 'required|exists:roles,id',
            'employeeId' => 'required|exists:employees,id',
            'status' => 'required|in:active,disabled',
            'username' => [
                'required',
                'string',
                'min:5',
                'max:20',
                'unique:users,username',
                'regex:/^[a-z0-9._-]+$/',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:20',
                'regex:/^(?=.*[0-9])(?=.*[a-z])(?=.*[A-Z])(?=.*[_\W])(?!.* ).{8,20}$/',
                'confirmed'
            ],
            'password_confirmation' => 'required|string',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'userRole.required' => 'User role is required.',
            'userRole.exists' => 'Selected user role is invalid.',
            'employeeId.required' => 'Employee selection is required.',
            'employeeId.exists' => 'Selected employee is invalid.',
            'status.required' => 'Status is required.',
            'status.in' => 'Status must be either active or disabled.',
            'username.required' => 'Username is required.',
            'username.min' => 'Username must be at least 5 characters.',
            'username.max' => 'Username cannot exceed 20 characters.',
            'username.unique' => 'This username is already taken.',
            'username.regex' => 'Username can only contain lowercase letters, numbers, dots, underscores, and hyphens.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.max' => 'Password cannot exceed 20 characters.',
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
            'password.confirmed' => 'Password confirmation does not match.',
            'password_confirmation.required' => 'Password confirmation is required.',
        ];
    }


}
