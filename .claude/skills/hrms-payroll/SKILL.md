---
name: hrms-payroll
description: Use when starting any Payroll module task in the General Core HRMS, or when touching existing Payroll routes, controllers, services, Vue pages, compensation data, or pay grades - to learn what exists in this repo today (mostly legacy) and which payroll skill covers the task.
---

# HRMS Payroll: Repo Entry Point

Payroll here is an **international, multi-country platform**: a Global Payroll Core plus one
Country Localization per country. The Philippines is the first localization.
This skill describes the repo's current state and routes you to the right skill.

| Task | Skill |
|---|---|
| Entities, jurisdictions, currencies, calendars, multi-country design | `global-payroll-architecture` |
| Gross-to-net, rounding, money, runs, finalization, corrections | `payroll-calculation-engine` |
| Encoding any country's law, rule metadata, verification | `payroll-compliance-localization` |
| Philippine rules (SSS, PhilHealth, Pag-IBIG, BIR, DOLE) | `payroll-country-philippines` |
| Permissions, migrations, UI, tests | `hrms-permissions`, `hrms-database-safety`, `hrms-frontend`, `hrms-testing` |

Architecture note: `docs/development/international-payroll-architecture.md`.

## Current state (verify with `php artisan route:list --except-vendor --path=payroll`)

- `routes/Payroll/web.php`, the `Web/Payroll/*` controllers, `app/Services/Payroll*Service.php`,
  the `pages/app/Payroll/**` pages, and `components/Payroll/*Tabs.vue` are **legacy and broken**.
  They import models that don't exist (`PayrollCalendar`, `PayrollDeduction`, `PayrollAccountType`,
  `OperatingUnit`, `JobStatus`, `PersonalInformation`), and their routes have no `can:` middleware.
  **Do not extend or import them.** Don't delete them without instruction. Ask whether new
  routes replace or coexist with the legacy route names (`payroll.generation.*`, `payroll.maintenance.*`).
- No payroll tables exist. Related data:
  - `job_offers.base_salary decimal(12,2)` + `currency char(3)` + `salary_frequency`
    (`hourly|daily|weekly|semi_monthly|monthly|annually`, with no bi-weekly). These are offer terms, not payroll.
  - `employee_movement_histories.salary decimal(12,2)` (no currency column).
  - `pay_grades` / `pay_grade_currencies` (`minimum_salary`/`maximum_salary` are `double`, a defect
    to flag, not copy).
- Multi-country prerequisites that are **missing** (design them as new tables via new migrations;
  don't overload existing ones):
  - legal entities: only one `organization_general_information` row, with a free-text `country`
  - a country on `locations`
  - a jurisdiction on `holidays`
  - employment assignments, tax residency
- Exists and is reusable: `countries` (`cou_code`, `iso3`, `numcode`; verify it holds ISO 3166 data),
  `nationalities` (**never** a jurisdiction input on its own), `ApprovalChainResolver`,
  `NumberSequenceService`, Time (`EmployeeWorkSchedule` → `WorkScheduleDay` → `Shift`, `Holiday`),
  and Leave (`leave_application_dates.is_paid`, `day_fraction`).
- `config/app.php` timezone is `Asia/Manila` and the locale is `en`. These are app defaults, **not** payroll
  defaults. Legal entities carry their own timezone and locale.
- Permissions: the seeder has a commented-out reserved payroll set (`payroll.*`, `compensation.*`).
  See `hrms-permissions` for the granular, entity-scoped model.

## Where new code goes (confirm on first implementation)

```
app/Services/Payroll/                       Global core (country-neutral)
app/Services/Payroll/Localizations/<CC>/    Country localization, ISO 3166-1 alpha-2 (PH first)
app/Http/Controllers/Web/Payroll/           New controllers (legacy ones stay untouched)
app/Http/Requests/Payroll/
resources/js/pages/app/Payroll/<Entity>/    New pages; utils/payroll.js for status maps
tests/{Unit,Feature}/Payroll/               Core suites; Localizations/<CC>/ per country
```

## Red flags

- A new payroll file imports a legacy payroll service, resource, or helper.
- Payroll code that only works if the currency is PHP or the country is PH.
- Starting implementation before the user approved the payroll data model (use `superpowers:brainstorming`
  and then `superpowers:writing-plans`).
