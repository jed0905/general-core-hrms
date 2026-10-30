<?php

namespace App\Services\Payroll\Rules\Methods;

use App\Services\Payroll\Support\Decimal;

/**
 * calculation_method "rate_with_floor_ceiling": base clamped to [floor, ceiling]
 * (either may be absent), multiplied by a rate. Unrounded.
 */
final class RateWithFloorCeiling
{
    public const METHOD = 'rate_with_floor_ceiling';

    /** @return array{base: string, amount: string} */
    public static function apply(string $base, string $rate, ?string $floor, ?string $ceiling): array
    {
        $clamped = $base;
        if ($floor !== null) {
            $clamped = Decimal::max($clamped, $floor);
        }
        if ($ceiling !== null) {
            $clamped = Decimal::min($clamped, $ceiling);
        }

        return ['base' => $clamped, 'amount' => Decimal::mul($clamped, $rate)];
    }
}
