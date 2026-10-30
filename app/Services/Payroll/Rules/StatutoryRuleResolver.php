<?php

namespace App\Services\Payroll\Rules;

use App\Models\StatutoryRuleVersion;

/**
 * Finds the statutory rule version in effect on a date. Verified versions win
 * over drafts; drafts are only considered when the caller allows them (previews,
 * runs that are flagged as using unverified rules and can't be finalized).
 * A jurisdiction-specific version wins over the national one.
 * Every version handed out is recorded so the run can snapshot and check them.
 */
class StatutoryRuleResolver
{
    /** @var array<string, ?StatutoryRuleVersion> */
    private array $cache = [];

    /** @var array<int, string> version id => verification status */
    private array $used = [];

    public function __construct(private readonly bool $allowDrafts = true) {}

    /**
     * @param  string|callable(string): string  $date  a fixed date, or a resolver that maps a
     *                                                 version's applies_by to the date that selects it
     */
    public function find(string $country, string $ruleKey, string|callable $date, ?string $jurisdiction = null, ?string $variant = null): ?StatutoryRuleVersion
    {
        $dates = is_callable($date)
            ? collect(StatutoryRuleVersion::APPLIES_BY)->mapWithKeys(fn ($by) => [$by => $date($by)])->all()
            : array_fill_keys(StatutoryRuleVersion::APPLIES_BY, $date);
        $cacheKey = implode('|', [$country, $ruleKey, $jurisdiction, $variant, ...array_values($dates)]);

        if (! array_key_exists($cacheKey, $this->cache)) {
            $statuses = $this->allowDrafts ? StatutoryRuleVersion::USABLE : StatutoryRuleVersion::LOCKED;

            // Each version is selected by the date its own applies_by names (pay date, period start/end...).
            $this->cache[$cacheKey] = StatutoryRuleVersion::query()
                ->with(['brackets', 'parameters'])
                ->where('country_code', $country)
                ->where('rule_key', $ruleKey)
                ->when($variant === null, fn ($q) => $q->whereNull('variant'), fn ($q) => $q->where('variant', $variant))
                ->where(fn ($q) => $q->whereNull('jurisdiction_code')->when($jurisdiction, fn ($q) => $q->orWhere('jurisdiction_code', $jurisdiction)))
                ->whereIn('verification_status', $statuses)
                ->where('effective_from', '<=', max($dates))
                ->get()
                ->filter(function (StatutoryRuleVersion $rule) use ($dates) {
                    $on = $dates[$rule->applies_by] ?? $dates['pay_date'];

                    return $rule->effective_from->toDateString() <= $on
                        && ($rule->effective_to === null || $rule->effective_to->toDateString() >= $on);
                })
                ->sortBy([
                    fn ($a, $b) => ($b->jurisdiction_code !== null) <=> ($a->jurisdiction_code !== null),
                    fn ($a, $b) => $b->isVerified() <=> $a->isVerified(),
                    fn ($a, $b) => [$b->version, $b->revision] <=> [$a->version, $a->revision],
                ])
                ->first();
        }

        $rule = $this->cache[$cacheKey];

        if ($rule) {
            $this->used[$rule->id] = $rule->verification_status;
        }

        return $rule;
    }

    public function require(string $country, string $ruleKey, string|callable $date, ?string $jurisdiction = null, ?string $variant = null): StatutoryRuleVersion
    {
        return $this->find($country, $ruleKey, $date, $jurisdiction, $variant)
            ?? throw MissingStatutoryRuleException::for($country, $ruleKey, is_callable($date) ? $date('pay_date') : $date, $variant, $jurisdiction);
    }

    /** @return array<int, string> */
    public function used(): array
    {
        return $this->used;
    }

    public function usesUnverified(): bool
    {
        return in_array(StatutoryRuleVersion::STATUS_DRAFT, $this->used, true);
    }
}
