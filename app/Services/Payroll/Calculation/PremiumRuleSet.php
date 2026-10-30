<?php

namespace App\Services\Payroll\Calculation;

/**
 * Premium-time rules in effect for a context, keyed by category. A category with
 * no rule produces no pay and an exception; there is never a default multiplier.
 */
final class PremiumRuleSet
{
    /** @var array<string, PremiumRule> */
    private array $rules = [];

    /** @param  array<int, PremiumRule>  $rules */
    public function __construct(array $rules = [])
    {
        foreach ($rules as $rule) {
            $this->rules[$rule->category] = $rule;
        }
    }

    public function for(string $category): ?PremiumRule
    {
        return $this->rules[$category] ?? null;
    }
}
