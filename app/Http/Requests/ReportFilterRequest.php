<?php

namespace App\Http\Requests;

use App\Services\Reports\Report;
use App\Services\Reports\ReportRegistry;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filters for one report. The report comes from the route (never from input),
 * and its category permission is re-checked here.
 */
class ReportFilterRequest extends FormRequest
{
    private ?Report $report = null;

    public function report(): Report
    {
        return $this->report ??= ReportRegistry::resolve($this->route()->defaults['report']);
    }

    public function authorize(): bool
    {
        return $this->user()->can($this->report()::permission());
    }

    public function rules(): array
    {
        return array_merge($this->report()->rules(), $this->report()->listRules());
    }

    /**
     * Validated filters with the report's defaults applied.
     */
    public function filters(): array
    {
        return $this->report()->normalize($this->validated());
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 25);
    }
}
