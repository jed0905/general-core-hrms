<?php

namespace App\Services\Payroll\Support;

use App\Models\Currency;
use RuntimeException;

/**
 * ISO 4217 minor units per currency, from the currencies table. An unknown or
 * inactive currency fails loudly; precision is never assumed.
 */
class CurrencyPrecision
{
    /** @var array<string, int> */
    private array $cache = [];

    public function minorUnits(string $currency): int
    {
        $code = strtoupper($currency);

        if (! array_key_exists($code, $this->cache)) {
            $minorUnits = Currency::where('code', $code)->where('is_active', true)->value('minor_units');

            if ($minorUnits === null) {
                throw new RuntimeException("Currency {$code} is not configured as an active ISO 4217 currency.");
            }

            $this->cache[$code] = (int) $minorUnits;
        }

        return $this->cache[$code];
    }
}
