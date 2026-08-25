<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeVoluntaryWorkFormRequest extends FormRequest
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
        // dd($this->request);
        return [
            'voluntaryWorks' => [
                'name_of_organization' => ['required', 'string', 'max:255'],
                'from' => ['required', 'date'],
                'to' => ['nullable', 'date'],
                'hours' => ['required', 'integer', 'min:1'],
                'position' => ['required', 'string', 'max:255'],
            ]
        ];
    }
}
