<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeaveApplicationFormRequest extends FormRequest
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
            'row_id' => 'required|integer',

            'status' => [
                'required',
                Rule::in([
                    'for approval',
                    'for disapproval',
                    'certified',
                    'approved',
                    'disapproved',
                    'cancelled'
                ]),
            ],

            'remarks' => [
                'nullable',
                'string',
                Rule::requiredIf(
                    fn() =>
                    in_array('status', [
                        'for disapproval',
                        'disapproved',
                        'cancelled'
                    ])
                ),
            ],
        ];
    }
}
