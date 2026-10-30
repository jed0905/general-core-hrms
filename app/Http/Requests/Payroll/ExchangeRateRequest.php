<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

/**
 * An exchange rate with its source: 1 base = rate quote.
 */
class ExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payroll.config.manage');
    }

    public function rules(): array
    {
        return [
            'base_currency' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'quote_currency' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'rate' => ['required', 'decimal:0,8', 'gt:0'],
            'rate_date' => ['required', 'date_format:Y-m-d'],
            'source' => ['required', 'string', 'max:100'],
        ];
    }
}
