<?php

namespace App\Services\Payroll\Rules\Methods;

use App\Models\StatutoryRuleBracket;
use App\Models\StatutoryRuleVersion;
use App\Services\Payroll\Support\Decimal;

/**
 * calculation_method "bracket_lookup": the bracket whose range contains the base.
 *
 * Brackets are contiguous: the matching bracket is the last one whose range_from
 * is <= base. range_to is only enforced on the last bracket (a published table
 * with "x to 4,249.99 / 4,250 and above" has no gap for 4,249.995).
 * Returns null when the base is below the first bracket or above a capped last one.
 */
final class BracketLookup
{
    public const METHOD = 'bracket_lookup';

    public static function find(StatutoryRuleVersion $rule, string $base): ?StatutoryRuleBracket
    {
        $brackets = $rule->brackets->sortBy('sequence')->values();
        $match = null;

        foreach ($brackets as $bracket) {
            if (Decimal::cmp($bracket->range_from, $base) <= 0) {
                $match = $bracket;
            }
        }

        $last = $brackets->last();
        if ($match && $match->is($last) && $last->range_to !== null && Decimal::cmp($base, $last->range_to) > 0) {
            return null;
        }

        return $match;
    }
}
