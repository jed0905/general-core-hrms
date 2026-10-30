<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Approve pending time inputs of a period.
 */
class ApproveInputsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payroll.input.approve');
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ];
    }
}
