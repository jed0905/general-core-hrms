<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Verify (notes optional) or reject (notes required) a draft rule version.
 */
class StatutoryRuleDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->routeIs('*.reject') ? 'reject' : 'verify', $this->route('statutoryRule'));
    }

    public function rules(): array
    {
        return [
            'notes' => [$this->routeIs('*.reject') ? 'required' : 'nullable', 'string', 'max:2000'],
        ];
    }
}
