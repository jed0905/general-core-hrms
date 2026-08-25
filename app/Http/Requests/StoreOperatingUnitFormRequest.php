<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOperatingUnitFormRequest extends FormRequest
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
            'prefix_id' => 'required|numeric',
            'name' => 'required|string',
            'shortcut' => 'required|string',
            'barangay' => 'required',
            'city_municipality' => 'required',
            'province' => 'required',
            'zip' => 'required'
        ];
    }
}
