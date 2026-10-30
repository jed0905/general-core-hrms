<?php

namespace App\Services\Payroll\Support;

use InvalidArgumentException;

/**
 * Decimal-string arithmetic for payroll (bcmath). Values in and out are numeric
 * strings; floats are never accepted. Intermediate results keep SCALE places and
 * are only rounded where a line is finalized (see round()).
 */
final class Decimal
{
    public const SCALE = 10;

    public const ROUNDING_MODES = ['half_up', 'half_even', 'up', 'down'];

    public static function add(string $a, string $b): string
    {
        return bcadd(self::of($a), self::of($b), self::SCALE);
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub(self::of($a), self::of($b), self::SCALE);
    }

    public static function mul(string $a, string $b): string
    {
        return bcmul(self::of($a), self::of($b), self::SCALE);
    }

    public static function div(string $a, string $b): string
    {
        if (self::isZero($b)) {
            throw new InvalidArgumentException('Division by zero in payroll calculation.');
        }

        return bcdiv(self::of($a), self::of($b), self::SCALE);
    }

    public static function neg(string $a): string
    {
        return bcmul(self::of($a), '-1', self::SCALE);
    }

    /** @param  iterable<string>  $values */
    public static function sum(iterable $values): string
    {
        $total = '0';
        foreach ($values as $value) {
            $total = self::add($total, $value);
        }

        return $total;
    }

    public static function cmp(string $a, string $b): int
    {
        return bccomp(self::of($a), self::of($b), self::SCALE);
    }

    public static function isZero(string $a): bool
    {
        return self::cmp($a, '0') === 0;
    }

    public static function isNegative(string $a): bool
    {
        return self::cmp($a, '0') < 0;
    }

    public static function min(string $a, string $b): string
    {
        return self::cmp($a, $b) <= 0 ? $a : $b;
    }

    public static function max(string $a, string $b): string
    {
        return self::cmp($a, $b) >= 0 ? $a : $b;
    }

    /**
     * Round to $places with an explicit mode. $places comes from the currency's
     * ISO 4217 minor units or from a statutory rule; the mode comes from the rule
     * or the pay group (never assumed).
     */
    public static function round(string $value, int $places, string $mode): string
    {
        if (! in_array($mode, self::ROUNDING_MODES, true)) {
            throw new InvalidArgumentException("Unknown rounding mode {$mode}.");
        }

        $value = self::of($value);
        $negative = bccomp($value, '0', self::SCALE) < 0;
        $abs = $negative ? bcmul($value, '-1', self::SCALE) : $value;
        $truncated = bcadd($abs, '0', $places);
        $remainder = bcsub($abs, $truncated, self::SCALE);
        $half = bcdiv('5', bcpow('10', (string) ($places + 1)), self::SCALE);
        $unit = bcpow('10', (string) -$places, $places);

        $up = match ($mode) {
            'half_up' => bccomp($remainder, $half, self::SCALE) >= 0,
            'half_even' => bccomp($remainder, $half, self::SCALE) > 0
                || (bccomp($remainder, $half, self::SCALE) === 0
                    && ((int) substr(bcmul($truncated, bcpow('10', (string) $places), 0), -1)) % 2 === 1),
            'up' => bccomp($remainder, '0', self::SCALE) > 0,
            'down' => false,
        };

        $result = $up ? bcadd($truncated, $unit, $places) : $truncated;

        if ($negative && bccomp($result, '0', $places) !== 0) {
            $result = bcmul($result, '-1', $places);
        }

        return $result;
    }

    /** Normalize to a fixed number of places without rounding semantics beyond truncation (display/storage of exact values). */
    public static function format(string $value, int $places): string
    {
        return bcadd(self::of($value), '0', $places);
    }

    /**
     * Validate a numeric string. Floats and non-numeric input are rejected so a
     * float can never slip into a money path.
     */
    public static function of(mixed $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (! is_string($value) || ! preg_match('/^-?\d+(\.\d+)?$/', trim($value))) {
            throw new InvalidArgumentException('Payroll decimals must be numeric strings; got '.get_debug_type($value).'.');
        }

        return trim($value);
    }
}
