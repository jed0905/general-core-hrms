# Payroll Operations Guide

How to set up and run payroll with the Global Payroll module. Design: `ARCHITECTURE.md`.
Philippines specifics: `PH_LOCALIZATION.md`.

## 1. Deploying

1. Back up the database.
2. `php artisan migrate` runs the nine `2026_10_30_*` migrations (new tables only; reference
   currencies, two number sequences, system pay components and payroll permissions are inserted
   idempotently). No existing table is altered. Nothing statutory is seeded.
3. `npm run build`.
4. Roles: the permissions migration grants defaults to existing roles (additive). Review them in
   Administration > Roles. Defaults: `payroll` prepares, calculates and reviews; `hr_director`
   approves, finalizes, reverses and verifies statutory rules; `hr_manager` records salary changes;
   every role sees its own payslips.

Rollback: `php artisan migrate:rollback --step=9` drops only the payroll tables and permissions.
**Never roll back once real payroll has been finalized**; finalized payroll is a legal record.

## 2. One-time setup (Payroll > Setup)

1. **Legal entity** per registered employer: ISO country, functional currency, timezone, locale.
   For a Philippine entity with semi-monthly/weekly pay groups, choose the contribution schedule
   (company policy).
2. **Pay group** per entity/frequency/currency: payroll country (selects the localization), hours per
   day and working days per year (rate divisors, company policy), proration method, rounding mode.
3. **Calendar**: "Generate year" per pay group (weekly/bi-weekly need the first period's start date).
   Adjust individual pay dates before any run exists for the period.
4. **Pay components**: create allowances, bonuses, commissions, loans, voluntary deductions and
   employer benefits, and set each one's **treatment per country** (the localization rejects untreated
   earnings).
5. **Exchange rates** if any compensation is in a currency other than the pay currency.
6. **Statutory rules** (Payroll > Statutory Rules): enter each rule version the localization lists, with
   its official source, issuance reference, effective dates and values; a second person verifies it.
   Runs can be calculated with drafts as previews but cannot be finalized.
7. **Payroll staff scope**: users without `payroll.scope.all` see only the legal entities assigned to
   them. Assign them per entity with "Payroll users" on the Legal entities tab (requires
   `payroll.scope.all` and `payroll.config.manage`).

## 3. Employees (Payroll > Employees)

Per employee: an **assignment** (pay group + work country/subdivision; give a reason when the work
country differs from the payroll country), an approved **salary record** (recorded by one person,
approved by another), optional **recurring items**, **tax residency**, **statutory profile** for the
payroll country, and a **bank account** in the pay currency.

## 4. Each pay period

1. **Inputs** (Payroll > Inputs): enter or import (CSV `employee_number,category,quantity`) time
   quantities: `days_worked`/`hours_worked` for daily/hourly-rated staff, `absence_days`, `late_hours`,
   `undertime_hours`, and premium hours under the localization's categories. Enter one-time
   adjustments. Someone with `payroll.input.approve` approves them. Approved leave is read from the
   Leave module automatically (unpaid dates become leave without pay).
2. **Run** (Payroll > Payroll Runs > New run): regular run for the period; Calculate; resolve every
   error exception (missing compensation, rule, treatment, exchange rate, negative net); exclude
   employees paid elsewhere; recalculate.
3. **Review** (a different payroll user), **Approve** (approver), **Finalize** (not the calculator).
   Finalizing numbers the payslips, marks adjustments applied, opens payments and publishes payslips
   to employees (My HR > My Payslips).
4. **Pay**: export the payment CSV or the ISO 20022 pain.001 file from the run's Payments tab,
   upload it to the bank yourself, then record each payment as paid/failed.
5. Close the period in Setup once everything is final.

## 5. Corrections

- Before finalization: return the run to draft, fix inputs/configuration, recalculate.
- After finalization: **never edit**. Either
  - add adjustments (and run a **supplemental** run for the period, or let the next regular run pay
    them), or
  - **reverse** the run (creates a negating run that is reviewed, approved and finalized), then pay the
    corrected amounts with a supplemental run.
- A salary change that is effective in the past does not recalculate finalized periods; pay the
  difference with adjustments.

## 6. Reports (Reports > Payroll)

Payroll Summary, Payroll Register, Earnings and Deductions, Statutory Contributions, Tax Withheld,
Payroll Cost, Payroll Reconciliation. Web, Excel, PDF and print, from finalized results only, within
the viewer's legal entities, per currency.

## 7. Adding a country

1. `app/Services/Payroll/Localizations/<CC>/<Name>Localization.php` implementing
   `App\Services\Payroll\Contracts\PayrollLocalization` (rule catalog, treatments, premium categories,
   profile fields, classify/statutory/tax hooks). Reuse the core methods in `Rules/Methods`.
2. Register it in `config/payroll.php` (`localizations`).
3. Enter and verify its rule versions with official sources.
4. Add `tests/Feature/Payroll/Localizations/<CC>/` with fictional values.
No change to the core is needed; if one seems needed, add a country-neutral extension point and test
it with the fictional `XX` localization.

## 8. Testing

```bash
php -d memory_limit=1G vendor/bin/phpunit tests/Unit/Payroll
php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Payroll
php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Payroll/Localizations/PH
```
Tests run on in-memory SQLite (forced by `phpunit.xml`). Use `vendor/bin/phpunit` with a memory flag:
`php artisan test` runs PHPUnit in a subprocess and hits the 128M limit in a pre-existing PDF test.

## 9. Known limitations

See `../autonomous-work/BLOCKERS.md` and `PH_LOCALIZATION.md` §3. In short: no DTR engine (time is
entered/imported), holidays have no jurisdiction, PH statutory values must be entered and verified,
no agency upload file layouts, no year-end tax adjustment, supplemental-run withholding is manual, and MySQL lock behavior under concurrent
finalization was not exercised with a concurrency test (the database unique keys still prevent
duplicate active runs).
