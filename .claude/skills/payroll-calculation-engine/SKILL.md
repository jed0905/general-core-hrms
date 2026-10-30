---
name: payroll-calculation-engine
description: Use when implementing or reviewing payroll calculation code in the General Core HRMS - gross-to-net, earnings, allowances, bonuses, commissions, overtime, holiday pay, night differential, deductions, employer contributions, tax hooks, proration, rounding, currency precision, money columns, payroll run calculation, finalization, adjustments, reversals, supplemental runs, or calculation snapshots.
---

# Payroll Calculation Engine

The country-neutral engine in the Global Payroll Core. It orchestrates the
calculation and does generic math. Every statutory decision is delegated to the
country localization (`payroll-compliance-localization`). Architecture context is in
`global-payroll-architecture`.

## Non-negotiables

1. **Decimal arithmetic only.** No `float`, `(float)`, `floatval`, `round()`, or
   `number_format()` in a calculation path, no money math in JS, and no `DOUBLE`/`FLOAT` columns.
2. **No statutory values or country branches in the engine.** Rates, brackets, ceilings,
   multipliers, thresholds, and rounding rules come from effective-dated rule versions
   through the localization.
3. **Employer contributions never reduce net pay.** They are a separate line class.
4. **Every line is reproducible**: it stores its inputs, the rule versions used, and the result.
5. **Finalized is immutable.** Change it only through adjustment, reversal, or supplemental runs.

## Line classes

| Class | Affects net? | Examples (generic) |
|---|---|---|
| `earning` | + | basic pay, allowance, bonus, commission, overtime, holiday premium, night differential |
| `employee_deduction` | − | statutory employee share, pension, loan repayment, voluntary deduction |
| `employee_tax` | − | income tax withheld (from the localization's tax provider) |
| `employer_contribution` | **no** | employer social insurance share, employer levies (an employer cost) |
| `informational` | no | taxable base, contributory base, YTD figures, leave balances on the payslip |

Invariants asserted on every payslip:
`gross = Σ earning`, `net = gross − Σ employee_deduction − Σ employee_tax`,
`employer_cost = gross + Σ employer_contribution`. All sums are over **rounded** line amounts.

## Calculation pipeline (per assignment per run)

1. **Resolve context**: assignment, legal entity, pay group, calendar period, payroll
   jurisdiction, tax residency, pay currency, and the localization for the jurisdiction's country.
   Resolve rule versions as of each rule's applicability date.
2. **Core earnings**: compensation components × quantities (days/hours from Time,
   unpaid leave from Leave), proration by the working-time basis that the
   entity or localization configures.
3. **Premium time** (overtime, holiday, rest day, night work): the core supplies hours by
   category from Time. The localization says which categories are eligible and gives the
   multipliers or rates and their combination order. The core does `rate × hours × multiplier`.
   A category with no rule in that jurisdiction produces no line. Never use a default multiplier.
4. **Localization: classify** each component's tax and contribution treatment and compute bases.
5. **Localization: statutory contributions**, as both employee lines and employer lines.
6. **Localization: income tax** (frequency tables, annualization, cumulative methods,
   year-end adjustment, whichever the country uses).
7. **Core: other deductions** (loans, voluntary) in configured priority, respecting any
   net-pay protection rule the localization provides.
8. **Round and total**: per-line rounding using the currency precision and the rounding mode the
   rule specifies. Then check the invariants.
9. **Snapshot**: persist the lines with inputs, rule version IDs, exchange rates, and the engine version.

Localization contract (shape only; finalize the names when payroll is built):

```php
interface PayrollLocalization
{
    /** ISO 3166-1 alpha-2, e.g. the registry key. */
    public function countryCode(): string;

    /** Tax/contribution treatment and statutory bases for this period's components. */
    public function classify(PayrollContext $context, array $components): ComponentTreatment;

    /** Employee and employer statutory lines (contributions, levies). @return list<PayLine> */
    public function statutoryLines(PayrollContext $context, ComponentTreatment $treatment): array;

    /** Income tax withholding lines. @return list<PayLine> */
    public function taxLines(PayrollContext $context, ComponentTreatment $treatment): array;

    /** Premium-time rules (eligibility, multipliers, combination) effective for the context. */
    public function premiumRules(PayrollContext $context): PremiumRuleSet;
}
```

The engine iterates the registry result. It never switches on country.

## Money and rounding

`bcmath` is enabled. PHP is 8.3, so use the procedural `bc*` functions; `BcMath\Number` arrived in 8.4.
`brick/math` is only a transitive dependency. Ask before requiring it directly.

```php
namespace App\Services\Payroll;

/** Decimal-string arithmetic. Values in and out are numeric strings; never floats. */
final class Decimal
{
    public const SCALE = 10;

    public static function add(string $a, string $b): string { return bcadd($a, $b, self::SCALE); }
    public static function sub(string $a, string $b): string { return bcsub($a, $b, self::SCALE); }
    public static function mul(string $a, string $b): string { return bcmul($a, $b, self::SCALE); }
    public static function div(string $a, string $b): string { return bcdiv($a, $b, self::SCALE); }

    /**
     * Round to $places with an explicit mode. $places comes from the currency's ISO 4217
     * minor units or from a statutory rule; the mode comes from the rule (never assumed).
     */
    public static function round(string $value, int $places, string $mode): string
    {
        $negative = bccomp($value, '0', self::SCALE) < 0;
        $abs = $negative ? bcmul($value, '-1', self::SCALE) : $value;
        $truncated = bcadd($abs, '0', $places);
        $remainder = bcsub($abs, $truncated, self::SCALE);
        $half = bcdiv('5', bcpow('10', (string) ($places + 1)), self::SCALE);
        $unit = bcpow('10', (string) -$places, $places);

        $up = match ($mode) {
            'half_up' => bccomp($remainder, $half, self::SCALE) >= 0,
            'half_even' => bccomp($remainder, $half, self::SCALE) > 0
                || (bccomp($remainder, $half, self::SCALE) === 0 && ((int) substr(bcmul($truncated, bcpow('10', (string) $places), 0), -1)) % 2 === 1),
            'up' => bccomp($remainder, '0', self::SCALE) > 0,
            'down' => false,
            default => throw new \InvalidArgumentException("Unknown rounding mode {$mode}"),
        };

        $result = $up ? bcadd($truncated, $unit, $places) : $truncated;

        return $negative ? bcmul($result, '-1', $places) : $result;
    }
}
```

- Money columns: `decimal(19, 4)` for stored amounts covers every ISO 4217 precision.
  Store the currency beside each amount. Rates go in `decimal(12, 8)` and quantities in `decimal(12, 4)`.
  (Existing non-payroll columns such as `job_offers.base_salary decimal(12,2)` stay as they are.)
- Casts: `'decimal:4'` (string). Validation: `['required', 'decimal:0,4']` plus a currency-aware
  precision check in the service.
- Calculate at `SCALE`, then round **once per line**. Never round intermediates unless a rule says to.

## Run lifecycle

`draft → calculated → reviewed → approved → finalized`, with `cancelled` before finalization.
Run types: `regular`, `supplemental` (off-cycle), `reversal`, `adjustment`.

- Calculation runs in one `DB::transaction()`. Lock the run (`lockForUpdate`) and regenerate only that
  non-final run's lines.
- Review, approval, and finalization are separate permissions held by different people
  (see `hrms-permissions`).
- Approvals: `ApprovalChainResolver` with a payroll module key, snapshotted into the run's approval rows.
- **Corrections**: a *reversal run* negates the original lines exactly, then a *supplemental
  run* or *adjustment lines* in the next regular run carry the corrected amounts. Each references
  the original line or run. Retro pay uses the **historical** rule versions for the period being corrected.
- Payslips and filings render from stored lines only. Never recompute them.

## Testing (with `hrms-testing`)

- Engine tests use a **fictional test localization** (user-assigned ISO code, e.g. `XX`) with
  fixture rules. This proves the engine works for any country and contains no real-country logic.
- Assert money as exact strings (`assertSame('1234.5000', ...)`). Cover currencies with 0, 2,
  and 3 minor units, every rounding mode, employer lines not touching net, and rule changes
  mid-period.
- Immutability: mutating a finalized run fails, and later rule edits leave stored lines unchanged.
