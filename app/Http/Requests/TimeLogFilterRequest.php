<?php

namespace App\Http\Requests;

use App\Services\AttendanceLogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters for the organization-wide Time Logs page (attendance.view).
 */
class TimeLogFilterRequest extends FormRequest
{
    public const PER_PAGE_OPTIONS = [25, 50, 100];

    public function authorize(): bool
    {
        return $this->user()->can('attendance.view');
    }

    public function rules(): array
    {
        return array_merge(app(AttendanceLogService::class)->rules(), [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', Rule::in(self::PER_PAGE_OPTIONS)],
        ]);
    }

    /**
     * Validated filters with the schema defaults (current month, sub-departments included) filled in.
     */
    public function filters(array $schema): array
    {
        $filters = [];

        foreach ($schema as $filter) {
            $value = $this->validated($filter['key']);
            $filters[$filter['key']] = ($value === null || $value === '') ? ($filter['default'] ?? null) : $value;
        }

        return $filters;
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? self::PER_PAGE_OPTIONS[0]);
    }
}
