<?php

namespace App\Services\Payroll\Support;

use App\Models\ExchangeRate;
use RuntimeException;

/**
 * Converts amounts between currencies with the latest stored rate on or before a
 * date (direct pair, else the inverse pair). Every rate used is recorded for the
 * payslip snapshot; a missing rate fails loudly.
 */
class ExchangeRateConverter
{
    /** @var array<string, array{base: string, quote: string, rate: string, rate_date: string, source: string, inverse: bool}> */
    private array $used = [];

    public function convert(string $amount, string $from, string $to, string $date): string
    {
        if ($from === $to) {
            return Decimal::of($amount);
        }

        $direct = $this->latest($from, $to, $date);
        if ($direct) {
            $this->record($direct, false);

            return Decimal::mul($amount, (string) $direct->rate);
        }

        $inverse = $this->latest($to, $from, $date);
        if ($inverse) {
            $this->record($inverse, true);

            return Decimal::div($amount, (string) $inverse->rate);
        }

        throw new RuntimeException("No exchange rate {$from}/{$to} on or before {$date}. Enter one in Payroll > Exchange Rates.");
    }

    /** @return array<int, array<string, mixed>> */
    public function used(): array
    {
        return array_values($this->used);
    }

    private function latest(string $base, string $quote, string $date): ?ExchangeRate
    {
        return ExchangeRate::where('base_currency', $base)
            ->where('quote_currency', $quote)
            ->where('rate_date', '<=', $date)
            ->orderByDesc('rate_date')
            ->orderByDesc('id')
            ->first();
    }

    private function record(ExchangeRate $rate, bool $inverse): void
    {
        $this->used[$rate->id] = [
            'id' => $rate->id,
            'base' => $rate->base_currency,
            'quote' => $rate->quote_currency,
            'rate' => (string) $rate->rate,
            'rate_date' => $rate->rate_date->toDateString(),
            'source' => $rate->source,
            'inverse' => $inverse,
        ];
    }
}
