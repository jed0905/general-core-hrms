<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSpecialLeaveRequest extends FormRequest
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
        return [
            'id' => 'required|exists:special_leaves,id',
            'leave_type_id' => 'required|exists:leaves,id',
            'name' => 'required|string|min:2|max:255',
            'shortcut' => 'required|string|min:2|max:10',
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'Special leave ID is required.',
            'id.exists' => 'Special leave not found.',
            'leave_type_id.required' => 'Special leave type ID is required.',
            'leave_type_id.exists' => 'Special leave type not found.',
            'name.required' => 'Special leave name is required.',
            'name.string' => 'Special leave name must be a string.',
            'name.min' => 'Special leave name must be at least 2 characters.',
            'name.max' => 'Special leave name cannot exceed 255 characters.',
            'shortcut.required' => 'Special leave shortcut is required.',
            'shortcut.string' => 'Special leave shortcut must be a string.',
            'shortcut.min' => 'Special leave shortcut must be at least 2 characters.',
            'shortcut.max' => 'Special leave shortcut cannot exceed 10 characters.',
        ];
    }
}
