<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOperatingUnitFormRequest extends FormRequest
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
            'prefix_id' => [
                'required',
                Rule::unique('operating_units', 'prefix_id')->ignore($this->route('id')),
            ],
            'name' => 'required',
            'shortcut' => 'required',
            'province' => 'required',
            'city_municipality' => 'required',
            'barangay' => 'required',
            'zip' => 'required',
        ];
    }
}
