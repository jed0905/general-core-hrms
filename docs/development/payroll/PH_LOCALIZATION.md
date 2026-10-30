# Philippines (PH) Payroll Localization

Code: `app/Services/Payroll/Localizations/PH/PhilippinesLocalization.php`
Tests (fictional values only): `tests/Feature/Payroll/Localizations/PH/`

> **Status: methods implemented, statutory values NOT entered.**
> No PH rate, bracket, ceiling, multiplier or peso amount is in the code, the migrations or the
> seeders. Each must be entered as a `PH.*` statutory rule version (Payroll > Statutory Rules) from the
> official issuance, then verified by a different user. A PH run cannot be finalized until every rule
> version it used is `verified`. This module is **not** described as compliant. The customer's
> Philippine payroll/tax adviser must review the configuration.

## 1. When PH applies

The assignment's `payroll_country_code = 'PH'` (the payroll jurisdiction, resolved from the legal
entity and work location, never from nationality). The pay group's `payroll_country_code` selects the
localization for its runs.

## 2. Rule keys the localization reads

| Rule key | Method | Data the version must carry | Where it is used |
|---|---|---|---|
| `PH.SSS.CONTRIBUTION` | `bracket_lookup` | One bracket per range of monthly compensation: `range_from`, `base_amount` (MSC, informational), `employee_amount`, `employer_amount`, `employer_extra_amount` (EC, employer-only). Store totals exactly as the schedule publishes them (incl. any provident-fund portion). | Lines `PH_SSS_EE`, `PH_SSS_ER`, `PH_SSS_EC` |
| `PH.PHILHEALTH.PREMIUM` | `rate_with_floor_ceiling` | Parameters `rate`, `floor`, `ceiling`, `employee_share` (fraction of the premium the employee pays). Base: monthly basic salary. | `PH_PHIC_EE`, `PH_PHIC_ER` |
| `PH.HDMF.CONTRIBUTION` | `bracket_lookup` | Brackets with `employee_rate`, `employer_rate`; parameter `max_fund_salary` caps the base. | `PH_HDMF_EE`, `PH_HDMF_ER` (+ `PH_HDMF_EE_VOL` from the employee profile, voluntary) |
| `PH.BIR.WITHHOLDING` | `progressive_table` | One version per `variant` = pay frequency (`daily`, `weekly`, `semi_monthly`, `monthly`). Brackets: `range_from`, `fixed_amount`, `rate`, `excess_over`. | `PH_WTAX`. A bi-weekly pay group has no BIR table variant and fails loudly. |
| `PH.BIR.NON_TAXABLE` | parameters | `benefits_exemption_ceiling` (annual). | 13th month pay and other benefits: exempt up to the ceiling per tax year, excess taxable |
| `PH.DOLE.PREMIUM_PAY` | multiplier | One parameter per premium category (see §4): the **full** multiplier of the hourly rate for those hours. | `PREMIUM_<CATEGORY>` lines |
| `PH.NWPC.MINIMUM_WAGE` | parameters | `daily_rate`; `jurisdiction_code` = the wage region code used as the assignment's `work_subdivision_code`. | Warning only (`PH_BELOW_MINIMUM_WAGE`); never changes pay |

`applies_by` (pay date, period start/end) is set per version from its issuance; the resolver selects
each version by the date its own `applies_by` names.

## 3. Calculation behavior

- **Treatments.** Contribution treatment keys: `basic` (SSS, Pag-IBIG and PhilHealth bases),
  `compensation` (SSS and Pag-IBIG only), `excluded`. Tax treatment keys: `taxable`, `non_taxable`,
  `thirteenth_month_other_benefits`. Engine components default: `BASIC`, `PAID_LEAVE` → taxable/basic;
  `ABSENCE`, `LWOP`, `LATE`, `UNDERTIME` → taxable/compensation (PhilHealth's monthly basic salary is
  not reduced by them, per PhilHealth Advisory 2025-0002); `PREMIUM_*` → taxable/compensation. Any other
  earning component **must** have a PH treatment configured, or the payslip errors (`PH_TREATMENT_MISSING`).
- **Taxable compensation** = taxable earnings + benefits above the remaining annual ceiling − mandatory
  employee contributions (SSS, PhilHealth, Pag-IBIG employee shares; voluntary extras are not deducted).
- **Minimum wage earners** (profile flag `minimum_wage_earner`): basic pay, paid leave, attendance
  deductions and premium lines are left out of taxable compensation. *Legal basis to confirm with the
  adviser: NIRC Sec. 24(A)(2) as amended by RA 10963 (TRAIN), RR 11-2018.*
- **Monthly contributions in non-monthly pay groups** follow company policy in the legal entity's
  `localization_settings.ph.contribution_schedule`: `last_period` (whole month in the last period paid in
  the month, base = all contributory earnings of the month) or `split` (projected and spread evenly; the
  last period trues up to the monthly amount). Missing setting → error. Monthly pay groups contribute
  every period.
- **Supplemental runs**: no contributions; withholding is not calculated automatically (warning
  `PH_SUPPLEMENTAL_TAX`); add it as an adjustment after review.
- **Not implemented**: year-end tax adjustment/annualization, 13th-month generation, service incentive
  leave conversion, statutory leave benefit differentials (e.g. maternity), de minimis per-item caps
  (classify such items `non_taxable` only within their cited limit), BIR 1601-C/1604-C/2316 and agency
  remittance file layouts (generic statutory contribution and tax reports exist; see `OPERATIONS.md`).

## 4. Premium categories

`overtime`, `night_differential`, `overtime_night`, `rest_day`, `rest_day_overtime`, `rest_day_night`,
`special_holiday`, `special_holiday_overtime`, `special_holiday_night`, `special_holiday_rest_day`,
`regular_holiday`, `regular_holiday_overtime`, `regular_holiday_night`, `regular_holiday_rest_day`,
`double_holiday`. The DOLE Handbook's guide tables **compound** premiums (e.g. a rest-day night
overtime hour multiplies the rest-day, night and overtime factors), so each category's parameter is
the full multiplier from the Handbook table, and time inputs must be recorded under the most specific
category. Hours are supplied as approved payroll time inputs (there is no DTR engine; see BLOCKERS B1).
Holiday pay for unworked regular holidays of daily-paid employees is not automated.

## 5. Official sources located (retrieved 2026-10-30, NOT yet verified)

These are the issuing-authority documents found by web search on 2026-10-30. The summaries below
come from search-result extracts, **not** from reading the PDFs end to end, so none of these figures
has been entered or verified. Whoever enters each rule version must read the document itself, cite
the section, and have a second person verify it.

| Rule key | Issuance (official URL) | What the search extract reported (to confirm against the document) |
|---|---|---|
| `PH.SSS.CONTRIBUTION` | SSS Circular 2024-006, *Schedule of SSS Contributions for All Business Employers and Employees Effective January 2025*: list at https://www.sss.gov.ph/sss-circulars/ ; table page https://www.sss.gov.ph/sss-contribution-table/ ; law RA 11199 | 15% of MSC (ER 10%, EE 5%), MSC range ₱5,000 to ₱35,000; EC ₱10 (MSC ≤ ₱14,500) or ₱30. Check for any 2026 schedule. |
| `PH.PHILHEALTH.PREMIUM` | PhilHealth Advisory 2025-0002 https://www.philhealth.gov.ph/advisories/2025/PA2025-0002.pdf ; contribution table https://www.philhealth.gov.ph/partners/employers/ContributionTable_v2.pdf ; Advisory 2026-0042 https://www.philhealth.gov.ph/advisories/2026/PA2026-0042.pdf ; PhilHealth Circular 2020-0005 | 5% of monthly basic salary, floor ₱10,000, ceiling ₱100,000, shared equally; basic salary excludes OT, allowances, 13th month, and is not reduced by absences/tardiness/undertime/unpaid leave. |
| `PH.HDMF.CONTRIBUTION` | HDMF Circular No. 460 https://www.pagibigfund.gov.ph/documents/Circular%20No.%20460%20-%20Guidelines%20on%20the%20Pag-IBIG%20Fund's%20Implementation%20of%20Increase%20in%20the%20MFS%20Effective%20February%202024.pdf ; RA 9679 | Maximum fund salary ₱10,000 from February 2024; EE 1% (≤ ₱1,500) or 2%, ER 2%. |
| `PH.BIR.WITHHOLDING` | RR 11-2018 https://bir-cdn.bir.gov.ph/local/pdf/RR%20No.%2011-2018.pdf ; Annex E (effective 2023-01-01 onwards) https://bir-cdn.bir.gov.ph/local/pdf/Annex%20E%20RR%2011-2018.pdf | Daily/weekly/semi-monthly/monthly tables; monthly bands at ₱20,833 / 33,333 / 66,667 / 166,667 / 666,667. Enter each frequency as its own variant from the Annex. |
| `PH.BIR.NON_TAXABLE` | RR 8-2018 https://bir-cdn.bir.gov.ph/local/pdf/RR%20No.%208-2018.pdf ; RR 11-2018 | 13th month pay and other benefits exempt up to ₱90,000 per year. |
| `PH.DOLE.PREMIUM_PAY` | DOLE BWC *Workers' Statutory Monetary Benefits Handbook, 2024 Edition* https://bwc.dole.gov.ph/wp-content/uploads/2024/10/Workers-Statutory-Monetary-Benefits-Handbook-2024-Edition.pdf ; Labor Code Book III https://blr.dole.gov.ph/2014/12/11/book-iii-conditions-of-employment/ | Night shift differential +10% (10 pm–6 am); OT +25% ordinary day, +30% on rest day/holiday rates; guide tables compound factors. |
| `PH.NWPC.MINIMUM_WAGE` | Regional wage orders, https://nwpc.dole.gov.ph | Not researched per region; enter per region from the current wage order. |

## 6. Statutory profile fields (encrypted at rest)

`tin`, `sss_number`, `philhealth_number`, `hdmf_number` (sensitive, never copied into payslip
snapshots), `minimum_wage_earner`, `hdmf_additional_employee_amount`.

## 7. Payslip content the localization lists

Employer name and address; employee name and ID; pay period and pay date; basic pay, overtime,
premium and night differential separately; each deduction separately (SSS, PhilHealth, Pag-IBIG,
withholding tax, others); gross and net pay.
