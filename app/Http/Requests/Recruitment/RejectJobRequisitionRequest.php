<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

class RejectJobRequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reject', $this->route('requisition'));
    }

    public function rules(): array
    {
        return ['remarks' => ['required', 'string', 'max:2000']];
    }

    public function attributes(): array
    {
        return ['remarks' => 'reason for rejection'];
    }
}
