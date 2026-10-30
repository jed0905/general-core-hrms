<?php

namespace App\Services\Payroll\Localization;

use App\Services\Payroll\Contracts\PayrollLocalization;
use InvalidArgumentException;

/**
 * Country code => localization. Populated from config('payroll.localizations');
 * adding a country is a new folder under Localizations/ plus one config entry.
 * Registered as a singleton (PayrollServiceProvider).
 */
class LocalizationRegistry
{
    /** @var array<string, PayrollLocalization> */
    private array $localizations = [];

    /** @param  array<string, class-string<PayrollLocalization>>  $classes */
    public function __construct(array $classes = [])
    {
        foreach ($classes as $class) {
            $this->register(app($class));
        }
    }

    public function register(PayrollLocalization $localization): void
    {
        $code = strtoupper($localization->countryCode());

        if (! preg_match('/^[A-Z]{2}$/', $code)) {
            throw new InvalidArgumentException("Localization country code must be ISO 3166-1 alpha-2; got {$code}.");
        }

        $this->localizations[$code] = $localization;
    }

    public function for(string $countryCode): ?PayrollLocalization
    {
        return $this->localizations[strtoupper($countryCode)] ?? null;
    }

    public function has(string $countryCode): bool
    {
        return $this->for($countryCode) !== null;
    }

    /** @return array<string, PayrollLocalization> */
    public function all(): array
    {
        return $this->localizations;
    }

    /** @return array<int, array{code: string, name: string}> */
    public function options(): array
    {
        return collect($this->localizations)
            ->map(fn (PayrollLocalization $l, string $code) => ['code' => $code, 'name' => $l->name()])
            ->values()
            ->all();
    }
}
