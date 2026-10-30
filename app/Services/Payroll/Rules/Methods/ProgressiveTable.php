<?php

namespace App\Services\Payroll\Rules\Methods;

use App\Models\StatutoryRuleVersion;
use App\Services\Payroll\Support\Decimal;

/**
 * calculation_method "progressive_table": for the bracket containing the base,
 * fixed_amount + (base - excess_over) x rate. excess_over defaults to the
 * bracket's range_from. Unrounded; null when no bracket applies.
 */
final class ProgressiveTable
{
    public const METHOD = 'progressive_table';

    /** @return array{amount: string, bracket_id: int}|null */
    public static function compute(StatutoryRuleVersion $rule, string $base): ?array
    {
        $bracket = BracketLookup::find($rule, $base);

        if (! $bracket) {
            return null;
        }

        $excessOver = $bracket->excess_over ?? $bracket->range_from;
        $excess = Decimal::max(Decimal::sub($base, $excessOver), '0');
        $amount = Decimal::add($bracket->fixed_amount ?? '0', Decimal::mul($excess, $bracket->rate ?? '0'));

        return ['amount' => $amount, 'bracket_id' => $bracket->id];
    }
}
