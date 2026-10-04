<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class ApproveJobRequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('approve', $this->route('requisition'));
    }

    public function rules(): array
    {
        return ['remarks' => ['nullable', 'string', 'max:2000']];
    }
}
