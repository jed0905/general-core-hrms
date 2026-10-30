# International Payroll Architecture

Status: **design direction, not yet implemented.** No payroll tables, services, or routes described
here exist yet. The existing `routes/Payroll/web.php` code is legacy and broken (see
`.claude/skills/hrms-payroll/SKILL.md`).

## 1. Decision

The HRMS payroll is a **multi-country platform** built as

```
Global Payroll Core  +  Country Payroll Localizations (one per country)
```

The Philippines (`PH`) is the **first** localization, not the architecture. Adding a country must
not require changes to the Global Payroll Core.

## 2. Separation of responsibilities

| Global Payroll Core (country-neutral) | Country Localization (one per ISO 3166-1 alpha-2 code) |
|---|---|
| Legal entities, pay groups, payroll calendars, periods | Income tax and its tax year |
| Employment assignments, jurisdiction resolution, tax-residency records | Social insurance, healthcare, pension, employment insurance |
| Compensation structures and pay components | Taxable / contributory treatment of components |
| Run lifecycle, approvals, finalization, immutability | Employer statutory contributions |
| Generic math: rate × quantity × multiplier, proration, decimal rounding | Minimum wage, overtime, holiday, rest-day, night work rules |
| Multi-currency amounts, exchange-rate snapshots | Statutory rounding rules |
| Snapshots, reversals, adjustments, supplemental runs | Payslip mandatory content, language |
| Consolidated multi-country reporting | Country filings, remittance files, record retention |

The core contains no country codes in conditionals, agency names, statutory rates, brackets, or
currency literals. It calls localizations through one contract (`PayrollLocalization`) resolved
from a registry keyed by country code.

## 3. Key concepts

- **Legal entity**: the registered employer. It has a country, identifiers, functional currency,
  timezone, and locale. *(Missing today. The repo has a single `organization_general_information` row.)*
- **Work location**: has a country and subdivision (ISO 3166-2). *(Missing today. `locations` has no country.)*
- **Employment assignment**: employee × legal entity × work location × effective dates. Concurrent
  assignments are allowed where legally applicable.
- **Payroll jurisdiction**: resolved from the entity, the work location, residency, and applicable rules.
  It is stored, effective-dated, and can be overridden with an approval.
- **Tax residency**: a separate, effective-dated, evidenced attribute.
- **Nationality never determines jurisdiction or residency on its own.**
- **Pay group / payroll calendar**: frequency, periods, cut-offs, and pay dates. They vary per pay group.
- **Tax year**: defined per localization. It isn't assumed to be January–December.
- **Working-time basis and holiday calendar**: per entity or jurisdiction. *(The existing `holidays`
  table has no jurisdiction, and Leave assumes 8 hours/day. Neither is reused as a payroll rule.)*

## 4. Calculation pipeline

1. Resolve the context: assignment, entity, pay group, period, jurisdiction, residency, currency,
   localization, and the rule versions as of each rule's applicability date.
2. Core earnings from compensation × quantities (Time, Leave), with proration.
3. Premium time: hours from Time, eligibility and multipliers from the localization.
4. Localization: classify components and compute bases.
5. Localization: statutory employee and employer lines.
6. Localization: income tax lines.
7. Core: other deductions in priority, respecting any localization net-pay protection.
8. Round each line (currency precision + rule rounding mode), total, check invariants.
9. Snapshot inputs, rule versions, exchange rates, and the engine version.

Line classes: `earning`, `employee_deduction`, `employee_tax`, `employer_contribution`,
`informational`. Net = gross − employee deductions − employee taxes. **Employer contributions never
reduce net pay.**

Arithmetic is decimal only (`bcmath` strings), and floats are never used for money. Each amount carries its ISO 4217
currency. Precision comes from the currency's minor units or the statutory rule. The rounding mode is explicit.

## 5. Statutory rules

Every legal rule is an effective-dated, versioned record with: country, jurisdiction, rule key,
category, official source, source URL, issuance reference, effective from/to, applicability date
basis, calculation method, parameters, version/revision, and verification status
(`draft` → `verified` → `superseded`).

- Values come only from official government issuances, confirmed by the user or their adviser.
  Nothing is invented or recalled from memory.
- Only verified rules can be used in a run that will be finalized.
- Verified rules are read-only. A change in law creates a new version.
- Historical payroll uses the rules effective for its own dates.
- No localization is described as "compliant" without verification. Each one lists its sources and gaps.

## 6. History and corrections

Finalized runs, lines, payslips, filings, and snapshots are immutable. Corrections are made through
reversal runs, adjustment lines, or supplemental (off-cycle) runs that reference the original.
Retroactive pay uses the historical rule versions for the corrected period.

## 7. Reporting

- Country reports, filings, and payslips are produced by each localization from finalized lines.
- Consolidated reports are produced by the core and grouped by entity, country, and currency. Converted totals
  use the stored exchange rates and are labelled. Payroll is never recalculated for reporting.

## 8. Standards and localization

ISO 3166 (countries, subdivisions), ISO 4217 (currencies, minor units), ISO 8601 (dates, times),
ISO 20022 (`pain.001` salary payments where banks support it), and ISO/IEC 27001-style security
controls with each country's data-protection law. These support interoperability and **do not
replace** country legislation.

The UI localizes language, date/time and number formats, decimal separators, and timezones per
viewer. Payslips and filings follow their localization. The `Asia/Manila` app timezone is not a payroll default.

## 9. Security and access

Granular permissions (compensation view vs edit; calculate / review / approve / finalize held by
different people; statutory rule manage vs verify; own payslip only), scoped by legal entity
through policies (spatie teams are disabled). Payroll data is sensitive personal data and is never logged.

## 10. Code and test layout (proposed, confirm on first implementation)

```
app/Services/Payroll/                              Global core
app/Services/Payroll/Localizations/<CC>/           Country localization (PH first)
tests/{Unit,Feature}/Payroll/                      Core suites, using a fictional "XX" localization
tests/{Unit,Feature}/Payroll/Localizations/<CC>/   Independent suite per country
```

## 11. Adding a country

1. New `Localizations/<CC>/` implementing `PayrollLocalization`.
2. A registry entry.
3. Rule versions with official sources, then verified.
4. An independent test suite.
5. Entity, pay group, calendar, and holiday configuration.

Needing a core change means a missing generic extension point. Add it country-neutrally,
tested with the fictional localization.

## 12. Known prerequisites (not yet built)

Legal entities; a country on work locations; a jurisdiction on holidays; employment assignments; tax
residency; currency and exchange-rate reference data; a payroll calendar per pay group; payroll
permissions (only reserved, commented-out names exist); a `double` → `decimal` fix for
`pay_grade_currencies` (flagged, not scheduled).

## 13. Skills

`.claude/skills/`: `hrms-payroll` (entry point), `global-payroll-architecture`,
`payroll-calculation-engine`, `payroll-compliance-localization`, `payroll-country-philippines`, plus
`hrms-architecture`, `hrms-database-safety`, `hrms-permissions`, `hrms-frontend`, `hrms-testing`.
