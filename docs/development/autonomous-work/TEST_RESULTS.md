# Test Results

## Baseline (Phase 0, 2026-10-30)

Command: `php -d memory_limit=1G vendor/bin/phpunit --testsuite Feature` (in-memory SQLite, forced by `phpunit.xml`).

- `php artisan test` crashes with "Allowed memory size of 134217728 bytes exhausted" inside dompdf (`Cpdf.php`)
  during a PDF report test: **pre-existing**, PHP CLI's 128M limit. `artisan test` runs PHPUnit in a
  subprocess, so `php -d memory_limit=...` must be passed to `vendor/bin/phpunit` directly.
- Result: **Tests: 525, Assertions: 6430, Errors: 6**. All 6 errors are in
  `tests/Feature/MaintenanceLeavesControllerTest.php` (legacy: missing `App\Models\Leave` and
  `maintenance.leaves.*` routes). Pre-existing, not touched.
- `tests/Unit` was empty.

## Payroll suites (final, 2026-10-30)

- `php -d memory_limit=1G vendor/bin/phpunit tests/Unit/Payroll`: **OK (22 tests, 38 assertions)**
- `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Payroll`: **OK (80 tests, 582 assertions)**, including
  the independent PH suite `tests/Feature/Payroll/Localizations/PH` (13 tests, fictional values).

| Suite | Covers |
|---|---|
| `Unit/Payroll/DecimalTest`, `RateConverterTest` | decimal-only math, float rejection, all rounding modes at 0/2/3 minor units, every compensation basis and frequency |
| `PayrollCalculationTest` | gross→net, employer lines separate, proration, mid-period salary change, effective dates, absences/late/OT, premium without rule, hourly pay, 4 frequencies, JPY/KWD precision, rounding mode, FX conversion and missing rate, missing rule, no localization, no compensation, negative net, multi-employee totals |
| `PayrollRunLifecycleTest` | full lifecycle + audit trail, separation of duties, no skipped steps, duplicate regular run (service + DB unique index), finalized immutability, compensation immutability, unverified rules block finalize, historical rule versions, snapshot contents, reversal, supplemental + adjustments applied, exclusion/inclusion, return to draft, disbursements opened |
| `PayrollTimeAndLeaveIntegrationTest` | approved unpaid leave → LWOP, pending/rejected/returned/cancelled ignored, out-of-period leave ignored, paid leave for daily-rated, working-day proration via Time schedules |
| `Localizations/PH/*` | SSS/PhilHealth/Pag-IBIG EE vs ER lines, absences vs PhilHealth base, semi-monthly last-period and split schedules, missing schedule, MWE, benefits ceiling, missing treatment, OT multiplier, `applies_by` selection, effective-date boundary, country isolation, minimum wage warning |
| `PayrollAccessTest` | permission matrix (7 roles × 7 pages), own-payslip privacy, unfinalized payslips hidden, entity scope, SoD over HTTP, rule verify by another person + supersede, catalog guard, compensation approval, currency precision, masked IDs/bank numbers, salary hidden without `compensation.view` |
| `PayrollReportsTest` | all 7 reports render, finalized-only totals, EE/ER contributions, reconciliation incl. reversal, scope, refusal, Excel export |
| `PayrollSetupAndInputsTest` | every payroll page renders, calendar generation (monthly/semi-monthly/bi-weekly), backdating guard, discard reopens record, two-person input approval, CSV import, closed periods, pain.001 export + payment tracking, overlapping verified rules refused, profile field validation, entity user assignment |

## Full suite (final, after all edits)

`php -d memory_limit=1G vendor/bin/phpunit` → **Tests: 641, Assertions: 7204, Errors: 6.**
The 6 errors are exactly the pre-existing baseline errors in `tests/Feature/MaintenanceLeavesControllerTest.php`
(legacy, missing `App\Models\Leave` / routes). **No new failures.**

Other checks: `php artisan route:list --except-vendor` registers all routes (payroll group + 28 payroll report
routes); `php artisan migrate --pretend` generates valid MySQL DDL for the 9 pending migrations (not executed);
`npm run build` succeeds; `vendor/bin/pint` passes.

Not done: MySQL concurrency test for finalization (needs a scratch MySQL server and approval); `php artisan migrate`
was **not** run against the dev database `general_hrms` (9 migrations pending).
