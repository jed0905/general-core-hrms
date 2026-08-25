<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSpecialLeaveRequest extends FormRequest
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
            'name' => 'required|string|min:2|max:255|unique:leaves,name',
            'shortcut' => 'required|string|min:2|max:10|unique:leaves,shortcut',
        ];
        
    }

    public function messages()
    {
        return [
            'name.required' => 'Name is required.',
            'name.min' => 'Name must be at least 2 characters.',
            'name.max' => 'Name cannot exceed 255 characters.',
            'name.unique' => 'Name already exists.',
            'shortcut.required' => 'Shortcut is required.',
            'shortcut.min' => 'Shortcut must be at least 2 characters.',
            'shortcut.max' => 'Shortcut cannot exceed 10 characters.',
            'shortcut.unique' => 'Shortcut already exists.',
        ];
    }
}
