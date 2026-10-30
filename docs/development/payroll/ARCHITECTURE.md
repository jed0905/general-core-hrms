# Payroll Module Architecture

Status: implemented in this branch (see `../autonomous-work/PROGRESS.md` for what is done).
Companion notes: `../international-payroll-architecture.md` (design direction),
`IMPLEMENTATION_PLAN.md`, `PH_LOCALIZATION.md`, `OPERATIONS.md`.

## 1. Shape

```
Global Payroll Core (country-neutral)          Country Localizations (one per ISO 3166-1 alpha-2)
  app/Services/Payroll/*                          app/Services/Payroll/Localizations/<CC>/*
  - entities, pay groups, calendars, periods      - tax / contribution treatment of components
  - assignments, residency, compensation          - statutory employee + employer lines
  - time & leave inputs, adjustments              - income tax lines
  - generic math, rounding, currency              - premium-time rules (OT, holiday, night)
  - run lifecycle, snapshots, immutability        - payslip requirements, filings
  - payslips, disbursements, consolidated reports
```

The core never branches on a country code and never contains a statutory value. It asks the
`LocalizationRegistry` for the `PayrollLocalization` registered for the pay group's payroll
country. A pay group whose country has no registered localization calculates core earnings and
deductions only, and the run carries a blocking exception: it cannot be approved.

## 2. Audit findings that shaped the design (verified against code, 2026-10-30)

| Finding | Consequence |
|---|---|
| `routes/Payroll/web.php` and `Web/Payroll/*`, `app/Services/Payroll*Service.php`, `pages/app/Payroll/{EmployeeMaintenance,Maintenance,PayrollGeneration}` are legacy: they import missing models and have no `can:` middleware. Route names `payroll.generation.*`, `payroll.maintenance.*`, `payroll.employee.maintenance.*`, URLs `payroll/calendar`, `payroll/deduction`, ... | Left untouched. New routes **coexist** under different names and URLs (`payroll.runs.*`, `payroll/configuration/...`). No new file imports legacy code. |
| No attendance/DTR engine exists. `employee_attendance_logs` holds raw punches keyed by biometric ID; late, undertime, absences and overtime are calculated nowhere (`AttendanceSummaryReport::notes()` says so). | Payroll does **not** build a DTR. Time inputs come through the `TimeInputProvider` contract. The shipped provider reads **approved** `payroll_time_inputs` (entered or imported by payroll staff). A future DTR module plugs in a new provider without touching the engine. |
| Schedules: `EmployeeWorkScheduleService::assignmentsQuery()` + `resolveDay()` are the single "is this a working day" rule. | Used for `working_days` proration. Not duplicated. |
| `holidays` has no country/jurisdiction column. | Holiday categories are not inferred by payroll. Premium hours (holiday, rest day, night) arrive as categorized time inputs. Documented limitation; `working_days` proration uses the single holiday list (documented). |
| Leave: `leave_applications.status` (`approved` is the only payable state) and `leave_application_dates` (`is_paid`, `duration_type`, `day_fraction`, `hours`). | `ApprovedLeaveProvider` reads approved applications only. Unpaid dates become leave-without-pay quantities. Pending/rejected/returned/cancelled are ignored. |
| One `organization_general_information` row with free-text country; `locations` has no country. | New `legal_entities` table (optionally linked to that row). Work country is stored on the **employment assignment** (`work_country_code`, `work_subdivision_code`) next to the optional `location_id`, so the Administration module is not altered. |
| `countries` exists (`cou_code` = alpha-2). No currency table. `pay_grade_currencies` uses `double`. | New `currencies` (ISO 4217 + minor units) and `exchange_rates`. `pay_grades` is reused by the new `salary_structure_grades` (decimal); the `double` table is left alone and flagged. |
| `ApprovalChainResolver` resolves approvers from a **subject employee's** supervisor chain. A payroll run has no subject employee. | Run approval is permission-based with enforced separation of duties (calculator ≠ reviewer ≠ approver; calculator ≠ finalizer), recorded in `payroll_run_events`. |
| `NumberSequenceService` exists. | Run numbers (`PR-{YYYY}-00001`) and payslip numbers (`PS-{YYYY}-000001`). |
| Reports module (`ReportRegistry`, `Report`) renders web/Excel/PDF/print from one class. | Payroll reports are `Report` subclasses in a new `payroll` category (`report.payroll`), scoped by legal entity inside `query()`. |
| `HandleInertiaRequests` doesn't share `success` flashes. | Fixed once in the middleware and shown by the layout (CLAUDE.md "known gap"). |
| Tests are forced to in-memory SQLite (`phpunit.xml`, `force="true"`), with a guard in `LeaveTestCase`. | `tests/Feature/Payroll/PayrollTestCase` extends that guard. |
| `bcmath` is enabled; PHP 8.3. | `Decimal` helper over `bc*` strings; no new package. |

## 3. Data model (all new tables; migrations dated `2026_10_30_*`)

### Reference
- `currencies` (code char(3) unique, name, minor_units, is_active). ISO 4217 reference rows for
  common currencies are inserted idempotently (standard data, not statutory).
- `exchange_rates` (base_currency, quote_currency, rate decimal(18,8), rate_date, source). Unique per
  (base, quote, date, source).

### Organization & jurisdiction
- `legal_entities`: code, name, legal_name, country_code (ISO 3166-1 alpha-2), subdivision_code,
  registration/tax identifiers, functional_currency, timezone, locale, fiscal_year_start_month,
  `localization_settings` (json; company policy the localization reads, e.g. how monthly
  contributions are spread across periods), organization_general_information_id, is_active.
- `pay_groups`: legal_entity_id, code, name, `payroll_country_code`, pay_frequency
  (`weekly|biweekly|semi_monthly|monthly`), pay_currency, working-time basis (`hours_per_day`,
  `annual_working_days` divisor), proration_method (`calendar_days|working_days`),
  rounding_mode (company rounding for non-statutory lines), is_active.
- `employment_assignments`: employee × legal entity × pay group × work location (location_id,
  work_country_code, work_subdivision_code) × effective dates; `payroll_country_code` (resolved
  jurisdiction) + optional `jurisdiction_override_reason`; is_primary.
- `employee_tax_residencies`: employee, country_code, residency_status, effective dates, evidence.
  Never derived from nationality or address.
- `employee_statutory_profiles`: employee, country_code, `attributes` (encrypted json; schema and
  validation owned by the localization, e.g. statutory IDs, withholding status), effective dates.
- `payroll_entity_user` : user ↔ legal entity scope for payroll administrators.

### Calendars
- `payroll_calendars` (pay_group, calendar_year), `payroll_periods` (calendar, pay_group,
  period_number, period_start, period_end, cutoff_date, pay_date, tax_year, status `open|closed`).

### Compensation
- `pay_components`: code, name, `line_class` (`earning|employee_deduction|employer_contribution|informational`),
  category (basic, allowance, bonus, commission, overtime, premium, other_earning, loan,
  voluntary_deduction, other_deduction, ...), legal_entity_id (null = all entities), priority, is_active.
- `pay_component_treatments`: per component per country, localization-defined `tax_treatment` and
  `contribution_treatment` keys.
- `salary_structures` + `salary_structure_grades` (reuses `pay_grades`): min/mid/max decimal(19,4),
  currency, basis, effective dates.
- `employee_compensations`: effective-dated salary history per assignment: basis
  (`annual|monthly|semi_monthly|biweekly|weekly|daily|hourly`), amount decimal(19,4), currency,
  pay_grade/salary_structure refs, reason, status `pending|approved`, approved_by. A new record closes
  the previous one; approved records referenced by a finalized run are never edited.
- `employee_pay_components`: recurring allowances/deductions per assignment (amount per period or
  per month), with optional `total_limit` for loans, effective dates.

### Statutory rules (any country)
- `statutory_rule_versions`: country_code, jurisdiction_code, rule_key, `variant` (e.g. a pay
  frequency for frequency-specific tables), category, name, calculation_method, applies_by,
  effective_from/to, version, revision, official_source, source_url, issuance_reference,
  retrieved_at, verification_status (`draft|verified|superseded|rejected`), verified_by/at, notes,
  created_by.
- `statutory_rule_brackets` (range_from, range_to, fixed_amount, rate, excess_over,
  employee_amount, employer_amount, employee_rate, employer_rate, extra_employer_amount),
  `statutory_rule_parameters` (key, decimal value or text value). No floats, no free-text numbers.

### Inputs
- `payroll_time_inputs`: period × employee × category (core: `days_worked`, `hours_worked`,
  `absence_days`, `late_hours`, `undertime_hours`; premium categories declared by the localization)
  × quantity, source, status `pending|approved`, approved_by.
- `payroll_adjustments`: one-time earnings/deductions (bonus, commission, corrections) for a period,
  with optional reference to a corrected run employee; status `pending|approved|applied`.

### Runs
- `payroll_runs`: run_number, legal_entity, pay_group, period, run_type
  (`regular|supplemental|reversal`), status (`draft|calculated|reviewed|approved|finalized|cancelled`),
  currency, `reverses_run_id`, actor + timestamp per step, engine_version, uses_unverified_rules,
  totals. Generated-column unique keys: one non-cancelled **regular** run per period; one
  non-cancelled **reversal** per original run.
- `payroll_run_employees` (one per employee per run = the payslip): totals, payslip_number,
  snapshot json (assignment, compensation, working-time basis, inputs, statutory profile keys,
  exchange rates, rule versions, engine version), status `included|excluded`.
- `payroll_run_lines`: line_class, code, description, quantity, rate, multiplier, amount, currency,
  tax/contribution treatment, statutory_rule_version_id, inputs json, `reverses_line_id`,
  `payroll_adjustment_id`, sequence.
- `payroll_run_exceptions` (severity `error|warning`): errors block review/approval.
- `payroll_run_events`: append-only audit of every lifecycle action with actor and remarks.
- `employee_bank_accounts` (encrypted account identifiers) and `payroll_disbursements`
  (per payslip: amount, currency, method, status `pending|exported|paid|failed`, reference).
- `payroll_audit_logs`: who changed configuration, compensation and statutory rules.

## 4. Calculation pipeline (per assignment per run)

`PayrollCalculator::calculateEmployee()`:

1. **Context**: assignment, entity, pay group, period, payroll country, tax residency, statutory
   profile, compensation segments, currency precision, exchange rates, localization.
2. **Base pay** (`BasePayCalculator`): segments = period ∩ assignment ∩ each compensation record.
   Salaried bases → period amount × segment share (calendar or working days). Daily/hourly bases →
   approved `days_worked`/`hours_worked` × rate, plus approved **paid** leave days × daily rate.
3. **Attendance deductions**: approved `absence_days`, leave-without-pay (approved unpaid leave
   dates), `late_hours`, `undertime_hours` × derived daily/hourly rate (salaried bases only).
4. **Premium time**: for each premium category with approved hours, the localization's
   `PremiumRuleSet` supplies the multiplier from a rule version; core does
   `hourly rate × hours × multiplier`. No rule → no line + an exception. Never a default multiplier.
5. **Recurring components and approved adjustments** (allowances, bonuses, commissions, loans).
6. **Localization `classify()`**: tax/contribution treatment of every line, statutory bases.
7. **Localization `statutoryLines()`**: employee deductions and employer contributions as separate lines.
8. **Localization `taxLines()`**: income tax withholding.
9. **Round & total**: each line rounded once (currency minor units; rule mode for statutory
   lines, pay-group mode otherwise). Invariants are asserted:
   `gross = Σ earning`, `net = gross − Σ employee_deduction − Σ employee_tax`,
   `employer_cost = gross + Σ employer_contribution`. Negative net raises an error exception.
10. **Snapshot**: lines + inputs + rule version IDs + engine version persisted with the payslip.

Money: `App\Services\Payroll\Support\Decimal` (bcmath, scale 10). Amounts are strings end to end;
columns `decimal(19,4)`, rates `decimal(19,8)`, quantities `decimal(12,4)`. Precision per currency
comes from `currencies.minor_units`. Rounding modes: `half_up`, `half_even`, `up`, `down`.
Intermediate values are never rounded unless a rule says so.

## 5. Run lifecycle and integrity

```
draft ──calculate──▶ calculated ──review──▶ reviewed ──approve──▶ approved ──finalize──▶ finalized
  ▲                      │ (recalculate)       │                     │
  └──────return──────────┴─────────────────────┴─────────────────────┘     cancel: any non-final
```

- Calculate/recalculate: one transaction, run row `lockForUpdate`, lines regenerated only for that
  non-final run.
- Separation of duties (policy): calculator can't review/approve/finalize; reviewer can't approve.
- Approve/finalize require no `error` exceptions. Finalize additionally requires every statutory
  rule version used to be `verified` (unverified → preview only).
- Finalize: lock run, re-check state, assign payslip numbers, mark adjustments `applied`, close
  status. Finalized runs, payslips and lines are guarded at model level (`updating`/`deleting`
  throw) as well as by services and policies.
- Corrections: **reversal run** (negates every line of the original, references them) and
  **supplemental run** (off-cycle: adjustments only), or adjustments in the next regular run.
- Duplicate protection: DB unique keys on generated columns + service checks inside the transaction.

## 6. Localization contract

`App\Services\Payroll\Contracts\PayrollLocalization`:
`countryCode()`, `name()`, `taxTreatments()`, `contributionTreatments()`, `premiumCategories()`,
`statutoryProfileFields()`, `classify()`, `statutoryLines()`, `taxLines()`, `premiumRules()`,
`requiredRuleKeys()`, `payslipRequirements()`.
Registered in `config/payroll.php` (`localizations` map, country code → class).
Generic rule methods (`BracketLookup`, `RateWithFloorCeiling`, `ProgressiveTable`) live once in
the core (`Rules/Methods`) and are reused by any localization.

## 7. Authorization

Spatie permissions (route `can:` middleware) + policies (`PayrollRunPolicy`, `PayslipPolicy`,
`StatutoryRuleVersionPolicy`, `EmployeeCompensationPolicy`) + entity scope (`PayrollScope`:
`payroll.scope.all` or rows in `payroll_entity_user`). No role names in code. Self-service:
`payslip.view_own` + `employee_id` ownership + finalized runs only. Salaries, bank numbers and
statutory IDs are never written to application logs; encrypted at rest where they are identifiers.

## 8. Standards

ISO 3166-1/-2 codes on entities, assignments, residencies, rules. ISO 4217 on every amount.
ISO 8601 dates in props, exports and snapshots. Disbursement export: generic CSV plus ISO 20022
`pain.001.001.03` customer credit transfer XML (file only; no bank connection).
