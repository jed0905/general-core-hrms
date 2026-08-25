<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserFormRequest extends FormRequest
{
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
        $rules = [
            'userRole' => 'required|exists:roles,id',
            'username' => 'required|string|max:255|unique:users,username,' . $this->route('id'),
            'status' => 'required|in:active,inactive',
        ];

        if ($this->filled('password')) {
            $rules['password'] = [
                'required',
                'string',
                'confirmed', // Must match the password confirmation field
                'regex:/^(?=.*[0-9])(?=.*[a-z])(?=.*[A-Z])(?=.*[_\W])(?!.* ).{8,20}$/'
            ];
        }

        return $rules;

    }
}
