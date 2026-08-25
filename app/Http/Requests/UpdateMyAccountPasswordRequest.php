<?php

namespace App\Http\Requests;

use App\Traits\SanitizesInput;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMyAccountPasswordRequest extends FormRequest
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
            'currentPassword' => 'required|string|max:255',
            'newPassword' => 'required|string|max:255|regex:/^(?=.*[0-9])(?=.*[a-z])(?=.*[A-Z])(?=.*[_\W])(?!.* ).{8,20}$/',
            'confirmNewPassword' => 'required|string|max:255|same:newPassword',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'currentPassword' => $this->cleanPassword($this->input('currentPassword')),
            'newPassword' => $this->cleanPassword($this->input('newPassword')),
            'confirmNewPassword' => $this->cleanPassword($this->input('confirmNewPassword')),
        ]);
    }
}
