---
name: payroll-compliance-localization
description: Use when encoding any country's payroll law in the General Core HRMS - income tax, social insurance, healthcare, pension, employment insurance, statutory deductions, employer contributions, minimum wage, overtime, holiday or rest-day pay, leave requirements, tax reporting, payroll filings, payslip requirements, record retention - or when creating a new country localization, entering statutory rates, or someone quotes a rate, bracket, ceiling, or threshold.
---

# Payroll Compliance Localization

How a country's payroll law is implemented, regardless of which country. The
engine that calls it is `payroll-calculation-engine`. Country-specific skills are
named `payroll-country-<country>` (first one: `payroll-country-philippines`).

## The rule

A statutory value or legal requirement enters the system only as an
**effective-dated rule version** carrying the metadata below and sourced from the
**official issuing authority**. It must be confirmed by the user or their adviser.
No source means no rule: the lookup fails loudly, and you ask
*"Which official issuance and effective date should this rule use?"*

**Never invent rates or legal requirements. Never state from memory that a value is current.**

| Rationalization | Reality |
|---|---|
| "I know this country's current rate" | Training data goes stale and rates change by law, regulation, and circular. Ask for the issuance. |
| "A placeholder with a TODO is fine" | Placeholders ship. Leave the rule absent so calculation fails loudly. |
| "This rule is the same in most countries" | Nothing statutory is universal. Each localization declares its own rules. |
| "A payroll blog or calculator has the table" | Not authoritative. Cite the government issuance itself. |
| "Tests need realistic numbers" | Tests use fictional fixtures. They prove the method, not the law. |

## Required metadata for every rule version

| Field | Notes |
|---|---|
| `country_code` | ISO 3166-1 alpha-2 |
| `jurisdiction_code` | ISO 3166-2 subdivision, or a localization-defined code (e.g. a wage region). `null` = national. |
| `rule_key` | Stable identifier, `<CC>.<AREA>.<RULE>` (e.g. `PH.SOCIAL_SECURITY.CONTRIBUTION`) |
| `category` | `income_tax`, `social_insurance`, `healthcare`, `pension`, `employment_insurance`, `statutory_deduction`, `employer_contribution`, `minimum_wage`, `overtime`, `holiday_pay`, `rest_day_pay`, `night_work`, `leave`, `rounding`, `reporting`, `retention` |
| `official_source` | Issuing authority (agency or legislature) |
| `source_url` | Official URL. Also record `retrieved_at`. |
| `issuance_reference` | Law, regulation, circular, or order number and section |
| `effective_from` / `effective_to` | `effective_to` null while open. Versions of one `rule_key` + jurisdiction never overlap. |
| `applies_by` | Which date selects the version: `pay_date`, `period_start`, `period_end`, or `earned_date`, as the issuance states |
| `calculation_method` | Identifier implemented in the localization code: `bracket_lookup`, `rate_with_floor_ceiling`, `progressive_table`, `fixed_amount`, `multiplier`, … |
| parameters / brackets | Decimal columns or child rows. Never floats or free-text numbers. |
| `version` / `revision` | Incremented per change. A correction to an unverified draft is a revision. A change in law is a new version. |
| `verification_status` | `draft` → `verified` → `superseded` (or `rejected`), with `verified_by`, `verified_at`, notes |

- Only **`verified`** versions can be used by a run that will be **finalized**. Draft rules may
  power a preview that is clearly marked as unverified.
- Verified versions are read-only. Changes create a new version. Close the old one's
  `effective_to` in the same transaction, using `lockForUpdate` on that `rule_key`.
- Historical payroll always uses the version effective for **its** dates, never the latest.

## What each localization provides (independently, only where the country requires it)

Income tax · social insurance · healthcare · retirement/pension · employment
insurance · other statutory deductions · employer contributions · minimum wage
validation · overtime · holiday and rest-day pay · night work · statutory leave
pay effects · tax year definition · payroll frequencies supported · tax reports
and filings · remittance files · payslip mandatory content and language ·
record retention period · data-protection constraints.

A category the country doesn't have is simply absent. Don't create zero-rate rules to "fill the slot".

## Code layout (proposal, confirm when payroll is first built)

```
app/Services/Payroll/                      Global core (country-neutral)
app/Services/Payroll/Localizations/<CC>/   One folder per ISO alpha-2 code, e.g. PH/
    <CC>Localization.php                   implements PayrollLocalization
    Calculators/                           one class per calculation_method the country needs
    Reports/                               filings, remittance files, payslip template data
tests/Unit/Payroll/Localizations/<CC>/     independent suite per country
tests/Feature/Payroll/Localizations/<CC>/
```

- A localization reads **only its own** `country_code` rules, and never another country's classes.
- Shared generic helpers (bracket lookup, floor/ceiling clamp) live in the core as
  country-neutral calculators. Countries reuse them by `calculation_method`.
  Don't copy them per country.
- Registration: one registry keyed by country code. Adding a country = new folder +
  registry entry + rules + tests. Nothing else in the core changes.

## Compliance claims

- Never describe a localization as "compliant" or "certified". Say what is implemented,
  which issuances it uses, and their verification status.
- Every localization's README or skill lists its sources and what's **not** implemented yet.
- Statutory configuration needs review by the customer's payroll/tax adviser. Tell the user so.

## Tests per localization

- One suite per country, runnable alone. Fixture rule values are fictional and labelled so.
- Each `calculation_method`: bracket edges, floors and ceilings, zero, maximum.
- Effective-date boundaries: the day before, on, and after `effective_from`, plus a period
  spanning two versions.
- History: a run finalized under version N still reproduces after version N+1 is verified.
- Employer lines never change net pay.
