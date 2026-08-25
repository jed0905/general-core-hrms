<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveTypeRequest extends FormRequest
{
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
    protected function prepareForValidation()
    {
        $this->merge([
            'name' => $this->cleanName($this->name),
            'shortcut' => $this->cleanShortcut($this->shortcut),
        ]);
    }

    /**
     * Clean the name field
     */
    private function cleanName($name)
    {
        if (!$name) return $name;
        
        // Remove extra whitespace and special characters
        $name = trim($name);
        $name = preg_replace('/\s+/', ' ', $name); // Multiple spaces to single space
        $name = preg_replace('/[^\w\s\-]/', '', $name); // Only alphanumeric, spaces, hyphens
        
        // Convert to title case
        $name = ucwords(strtolower($name));
        
        return $name;
    }

    /**
     * Clean the shortcut field
     */
    private function cleanShortcut($shortcut)
    {
        if (!$shortcut) return $shortcut;
        
        // Remove spaces and special characters, convert to uppercase
        $shortcut = trim($shortcut);
        $shortcut = preg_replace('/[^A-Za-z0-9]/', '', $shortcut);
        $shortcut = strtoupper($shortcut);
        
        // Limit to 10 characters
        $shortcut = substr($shortcut, 0, 10);
        
        return $shortcut;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /* There is no migration that has a name leave type */
        /* We have leaves migration */
        /* Name, Shortcut */
        return [
            'name' => 'required|string|min:2|max:255|unique:leaves,name|regex:/^[a-zA-Z\s\-]+$/',
            'shortcut' => 'required|string|min:2|max:10|unique:leaves,shortcut|regex:/^[A-Z0-9]+$/',
            // 'is_entitlement_situational' => 'required|boolean',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages()
    {
        return [
            'name.required' => 'Leave type name is required.',
            'name.min' => 'Leave type name must be at least 2 characters.',
            'name.max' => 'Leave type name cannot exceed 255 characters.',
            'name.unique' => 'This leave type name already exists.',
            'name.regex' => 'Leave type name can only contain letters, spaces, and hyphens.',
            
            'shortcut.required' => 'Shortcut is required.',
            'shortcut.min' => 'Shortcut must be at least 2 characters.',
            'shortcut.max' => 'Shortcut cannot exceed 10 characters.',
            'shortcut.unique' => 'This shortcut already exists.',
            'shortcut.regex' => 'Shortcut can only contain uppercase letters and numbers.',
        ];
    }
}
