# Payroll Implementation Plan

Design: `ARCHITECTURE.md`. Tracking: `../autonomous-work/PROGRESS.md`.

| Phase | Scope | Main artifacts | Tests |
|---|---|---|---|
| 0 | Audit, architecture, plan, baseline | `ARCHITECTURE.md`, this file, autonomous-work files | Baseline run of existing suites |
| 1 | Foundation | Migrations (reference, organization, calendars, compensation, rules, inputs, runs, disbursements, permissions); models; `PayrollScope`; config services + controllers + FormRequests for legal entities, pay groups, calendars/periods, pay components, salary structures, compensation, assignments, residency, statutory profiles, exchange rates; permissions seeder + migration; sidebar | Unit: `Decimal`, rate conversion. Feature: compensation effective dating, calendar generation, permissions |
| 2 | Global engine | `Decimal`, `CurrencyPrecision`, `RateConverter`, `PayrollContext`, `PayLine`, `PayrollCalculator`, `LocalizationRegistry`, `StatutoryRuleResolver`, generic rule methods | Unit + feature with fictional `XX` localization: gross/net, employer lines, rounding modes, 0/2/3-minor-unit currencies, multiple frequencies, multi-currency |
| 3 | Time & leave | `TimeInputProvider` + `PayrollTimeInputProvider`, `ApprovedLeaveProvider`, working-day proration via `EmployeeWorkScheduleService`, time input + adjustment services/UI | Approved vs pending leave, LWOP, absences, late/undertime, OT premium via rules |
| 4 | Philippines | `Localizations/PH/*` (SSS, PhilHealth, HDMF, BIR withholding, premium pay, statutory profile fields, payslip requirements), `PH_LOCALIZATION.md` with official sources | Independent PH suite with fictional fixture values, effective-date boundaries, historical versions |
| 5 | Processing | `PayrollRunService` (create, include/exclude, calculate, review, approve, return, cancel, finalize, reverse, supplemental), `PayrollRunPolicy`, run pages | Lifecycle, SoD, immutability, duplicate prevention, reversal, multi-employee |
| 6 | Payslips & disbursement | Payslip service + PDF, my payslips, bank accounts, disbursement records, CSV + pain.001 export | Privacy (own only, finalized only), export contents |
| 7 | Reports | `Reports/Payroll/*` registered in `ReportRegistry` (summary, register, earnings & deductions, contributions, tax, cost, reconciliation, statutory remittance by rule) | Scope + totals |
| 8 | Hardening | Full payroll suite, regression run of existing suites, `route:list`, `npm run build`, pint, docs (`OPERATIONS.md`) | Whole suite |

## Conventions locked in

- Namespaces: `App\Services\Payroll\...`, `App\Http\Controllers\Web\Payroll\<New>Controller`
  (legacy controllers in the same folder are untouched; new names never collide),
  `App\Http\Requests\Payroll\...`, `App\Policies\Payroll...`.
- Routes: appended to `routes/Payroll/web.php` in a separate `['auth','verified']` group with
  names `payroll.<area>.*`; no name or URL collides with the legacy group.
- Pages: `resources/js/pages/app/Payroll/<Area>/...`; helpers `resources/js/utils/payroll.js`,
  `resources/js/utils/money.js`.
- Tests: `tests/Unit/Payroll`, `tests/Feature/Payroll`, `tests/{Unit,Feature}/Payroll/Localizations/PH`.
