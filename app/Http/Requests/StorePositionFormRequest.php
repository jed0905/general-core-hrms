<?php

namespace App\Http\Requests;

use App\Models\Position;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class StorePositionFormRequest extends FormRequest
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
        // dd($this->all());
        return [
            'government_positions_id' => 'required|numeric|exists:government_positions,id',
            'plantilla_item_number' => [
                'nullable',
                'string',
                Rule::unique('positions', 'plantilla_item_number'),
            ],
            'salary_grade_id' => 'nullable|numeric|exists:salary_grades,id',
            'operating_unit_id' => [
                'required',
                'numeric',
                'exists:operating_units,id',
                function ($attribute, $value, $fail) {
                    $exists = Position::where('government_positions_id', request('government_positions_id'))
                        ->where('salary_grade_id', request('salary_grade_id'))
                        ->where('operating_unit_id', request('operating_unit_id'))
                        ->whereNull('plantilla_item_number')
                        ->exists();

                    if ($exists && request('plantilla_item_number') === null) {
                        $fail('This position already exist in the operating unit without a plantilla item number.');
                    }
                },
            ],
        ];
    }
}
