---
name: global-payroll-architecture
description: Use when designing payroll data models or flows in the General Core HRMS that involve countries, legal entities, employing organizations, currencies, pay groups, payroll calendars, pay frequencies, tax years, payroll jurisdiction, tax residency, cross-border or multi-country employees, consolidated payroll reporting, or adding a new payroll country.
---

# Global Payroll Architecture

The HRMS payroll is **one Global Payroll Core plus one Country Localization per
country**. The Philippines is the first localization, not the architecture. Any
design that only works for one country is wrong, even if that country is the only
one configured today.

Related skills: `payroll-calculation-engine` (how a run calculates),
`payroll-compliance-localization` (how a country's law is encoded),
`payroll-country-philippines` (first country), `hrms-payroll` (state of this repo).
The full architecture note is `docs/development/international-payroll-architecture.md`.

## The boundary

| Global Payroll Core owns | Country Localization owns |
|---|---|
| Legal entities, pay groups, payroll calendars, periods, runs, run lifecycle and approvals | Income tax, social insurance, healthcare, pension, employment insurance |
| Compensation records, pay components, quantities from Time/Leave | Which components are taxable or contributory, and on what base |
| Generic math: rate × quantity × multiplier, proration, rounding mechanics | Statutory rates, brackets, ceilings, floors, multipliers, rounding rules |
| Money and currency handling, exchange-rate snapshots | Minimum wage, overtime, holiday, rest-day, night work rules |
| Snapshots, immutability, adjustments, reversals, supplemental runs | Payslip mandatory content, filings, remittance files, retention periods |
| Consolidated multi-country reporting | Country reports and filings |

The core must contain **no country names, country codes in conditionals,
agency names, or statutory values.** `if ($country === 'PH')` in core code is a
defect. The core calls a localization through a contract that is resolved by country code.

## Core concepts (each one is an explicit, configurable record)

| Concept | Meaning | Notes |
|---|---|---|
| **Legal entity** (employing organization) | The registered employer that pays wages and files | Country (ISO 3166-1 alpha-2), registration/tax identifiers, functional currency, timezone, default locale. **Missing today**: the repo has one `organization_general_information` row. Multi-entity needs a new table. Don't overload that row. |
| **Work location** | Where work is physically performed | Country plus subdivision (ISO 3166-2) where taxes or minimum wages are regional. **Today** `locations` has no country column. |
| **Employment assignment** | Employee × legal entity × work location × effective dates | An employee can have consecutive assignments, or concurrent ones where legally permitted (e.g. split payroll). Each is paid by its own entity and pay group. |
| **Payroll jurisdiction** | Whose payroll law applies to an assignment | **Resolved** from the entity, the work location, residency, and applicable rules (treaties, secondment, posting rules), and stored on the assignment with an effective date. Can be overridden with an approval and a reason. |
| **Tax residency** | Where the employee is tax resident | A separate, effective-dated attribute of the employee, configurable and evidenced. It is **not** derived from nationality, address, or jurisdiction. |
| **Nationality** | Citizenship (`employees.emp_nationality_id`) | **Never** decides jurisdiction or residency on its own. A localization may read it only where a cited rule makes nationality an input. |
| **Pay group** | Employees of one entity sharing a calendar, frequency, and pay currency | The unit a run processes |
| **Payroll calendar** | Periods, cut-offs, pay dates for a pay group | Frequencies vary: weekly, bi-weekly, semi-monthly, monthly, four-weekly, others. Never assume one. (`job_offers.salary_frequency` lacks bi-weekly. Payroll needs its own list.) |
| **Tax year / fiscal year** | Per country localization (and per entity for fiscal) | Never assume January–December. |
| **Compensation structure** | Effective-dated pay components per assignment: salaried, hourly, daily, commission, allowances | Amount + ISO 4217 currency + frequency |
| **Working-time basis** | Standard hours/day, days/week, and divisors for daily or hourly rates | Configured per entity or localization, never a global "8 hours". Leave's 8-hour assumption must not be reused. |
| **Holiday calendar** | Holidays per jurisdiction | **Today** `holidays` has no country or jurisdiction column. Multi-country pay needs one before holiday pay can be calculated. |

## Multiple currencies

- Every amount is stored with an ISO 4217 code (`char(3)`). Never assume PHP or any default currency.
- The pay currency comes from the pay group. Contract currency can differ. Conversion uses an
  **effective-dated exchange rate snapshot** (rate, source, rate date) stored on the run.
- Precision comes from a currency reference record (ISO 4217 minor units), not from a hardcoded `2`.
- Statutory computations run in the currency the localization requires.
- Consolidated reporting converts into a chosen reporting currency using stored rates.
  It never recalculates payroll.

## Effective dating and history

- Everything legal or financial is versioned with `effective_from`/`effective_to`:
  assignments, jurisdictions, residency, compensation, calendars, rules.
- A run resolves all of them **as of the dates the rule says apply** (pay date, period, or
  earned date, as the localization declares), then snapshots what it used.
- Finalized runs are never recalculated with newer rules. Retroactive changes produce adjustment lines in a later run.

## Reporting

- **Country reports, filings, payslips**: produced by the localization from finalized lines only.
- **Global/consolidated reports**: produced by the core from finalized lines, grouped by entity,
  country, cost center, and currency, with converted totals clearly labelled with the rate used.
- Payslips are rendered per localization template (mandatory fields and language vary) from stored lines.

## Standards (interoperability, not law)

| Standard | Use |
|---|---|
| ISO 3166-1 alpha-2 / ISO 3166-2 | Country and subdivision codes for entities, locations, jurisdictions, rules. A `countries` table already exists (`cou_code`, `iso3`, `numcode`). Verify its contents before relying on it. |
| ISO 4217 | Currency codes and minor units |
| ISO 8601 | Dates (`YYYY-MM-DD`) and timestamps in APIs, exports, and snapshots. Pay dates are dates in the entity's timezone, and instants are stored in UTC. The app timezone is `Asia/Manila`, so don't treat it as every entity's timezone. |
| ISO 20022 (e.g. `pain.001` credit transfers) | Salary payment files where the bank supports them. Otherwise use the bank or country format the localization defines. |
| ISO/IEC 27001-style controls; privacy laws per country | Payroll data is sensitive personal data, and cross-border transfer rules depend on the country |

Localize language, date/time/number formats, decimal separators, and timezones per
viewer (UI) and per localization (payslips and filings).

## Adding a country (must not touch the core)

1. Create the localization (see `payroll-compliance-localization`) under its ISO code.
2. Register it in the localization registry.
3. Load its rule versions with official sources, then verify them.
4. Add its independent test suite.
5. Configure entities, pay groups, calendars, and holiday calendars.

If step 1 needs a core change, the core is missing a generic extension point. Add the
generic hook (country-neutral, tested with a fictional country) instead of a country branch.

## Red flags

- A currency literal (`'PHP'`, `'USD'`) or `en-PH` in core or shared UI code.
- Jurisdiction or tax derived from `emp_nationality_id`, or residency from the home address.
- "Most employees are monthly, so assume monthly."
- One global holiday list, one global standard workday, one global tax year.
