<?php

namespace App\Services\Reports\Payroll;

use App\Models\LegalEntity;
use App\Models\PayGroup;
use App\Models\PayrollRun;
use App\Services\Payroll\PayrollScope;
use App\Services\Payroll\Support\CurrencyPrecision;
use App\Services\Payroll\Support\Decimal;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * Shared filters, legal-entity scoping and money display for payroll reports.
 * Reports read stored results of FINALIZED runs only (unless a report says
 * otherwise) and never recalculate. Totals are per currency; nothing is
 * converted or added across currencies.
 */
trait PayrollReportSupport
{
    public static function category(): string
    {
        return 'payroll';
    }

    protected function periodFilters(): array
    {
        $entities = $this->scopedEntities();

        return [
            ['key' => 'date_from', 'label' => 'Pay date from', 'type' => 'date', 'required' => true, 'default' => now()->startOfYear()->toDateString()],
            ['key' => 'date_to', 'label' => 'Pay date to', 'type' => 'date', 'required' => true, 'default' => now()->endOfYear()->toDateString()],
            ['key' => 'legal_entity_id', 'label' => 'Legal entity', 'type' => 'select', 'options' => $entities->map(fn ($e) => ['value' => $e->id, 'title' => "{$e->name} ({$e->country_code})"])->all()],
            ['key' => 'pay_group_id', 'label' => 'Pay group', 'type' => 'select', 'options' => PayGroup::whereIn('legal_entity_id', $entities->pluck('id'))->orderBy('name')->get(['id', 'name'])->map(fn ($g) => ['value' => $g->id, 'title' => $g->name])->all()],
        ];
    }

    protected function periodRules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'legal_entity_id' => ['nullable', 'integer', 'exists:legal_entities,id'],
            'pay_group_id' => ['nullable', 'integer', 'exists:pay_groups,id'],
        ];
    }

    /**
     * Runs (alias r) joined to periods (alias pp), limited to the viewer's legal
     * entities, the pay-date range and, by default, finalized runs.
     */
    protected function scopedRuns(Builder $query, array $filters, bool $finalizedOnly = true): Builder
    {
        $entityIds = app(PayrollScope::class)->entityIds(auth()->user());

        return $query
            ->when($entityIds !== null, fn ($q) => $q->whereIn('r.legal_entity_id', $entityIds))
            ->when($finalizedOnly, fn ($q) => $q->where('r.status', PayrollRun::STATUS_FINALIZED))
            ->whereBetween('pp.pay_date', [$filters['date_from'], $filters['date_to']])
            ->when(! empty($filters['legal_entity_id']), fn ($q) => $q->where('r.legal_entity_id', $filters['legal_entity_id']))
            ->when(! empty($filters['pay_group_id']), fn ($q) => $q->where('r.pay_group_id', $filters['pay_group_id']));
    }

    protected function scopedEntities()
    {
        $entityIds = auth()->check() ? app(PayrollScope::class)->entityIds(auth()->user()) : [];

        return LegalEntity::query()->when($entityIds !== null, fn ($q) => $q->whereIn('id', $entityIds))->orderBy('name')->get(['id', 'name', 'country_code']);
    }

    /**
     * Display a stored amount at the currency's precision. MySQL returns SUM() of
     * decimal columns as exact strings; SQLite (tests) returns floats, which are
     * normalized here for display only.
     */
    protected function money(mixed $value, string $currency): string
    {
        $value = is_string($value) ? $value : sprintf('%.4F', (float) ($value ?? 0));

        return Decimal::format($value, app(CurrencyPrecision::class)->minorUnits($currency));
    }

    protected function notesCommon(): array
    {
        return [
            'Finalized runs only, by pay date. Reversal runs appear with negative amounts so corrected periods net out.',
            'Amounts are in each run\'s pay currency and are never added across currencies.',
        ];
    }
}
