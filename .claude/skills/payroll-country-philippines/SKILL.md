---
name: payroll-country-philippines
description: Use only when payroll work in the General Core HRMS concerns the Philippines (country code PH) localization - SSS, PhilHealth, Pag-IBIG/HDMF, BIR withholding tax on compensation, DOLE labor standards, regional minimum wage orders, overtime, holiday and rest-day premiums, night shift differential, 13th month pay, Philippine statutory leaves, BIR Form 2316/1601-C/1604-C, or Philippine remittance reports.
---

# Payroll Country: Philippines (PH)

**Country-specific.** Everything here applies only to the PH localization
(`app/Services/Payroll/Localizations/PH/`, `country_code = 'PH'`). None of it belongs in the
Global Payroll Core, in shared services, or in another country's localization. The generic rule
model, metadata, and verification flow are in `payroll-compliance-localization`, and the calculation
pipeline is in `payroll-calculation-engine`.

**This skill contains no rates, brackets, ceilings, thresholds, multipliers, or peso amounts.**
They change by law, circular, and wage order. Each must come from the official issuance, confirmed by the user.

## When PH applies

The PH localization applies when an assignment's **payroll jurisdiction** resolves to PH
(legal entity, work location, and applicable rules), **not** because the employee is
Filipino. A Filipino working for a foreign entity abroad, or a foreign national working in the
Philippines, is decided by jurisdiction rules and tax residency, never by nationality alone.

## Official sources (primary only)

| Area | Authority and site | Governing law (verify amendments) | Cite |
|---|---|---|---|
| Social security contributions (incl. EC, and any provident-fund component in the schedule) | Social Security System, https://www.sss.gov.ph | RA 11199 (Social Security Act of 2018) | SSS Circular number, contribution schedule, effectivity |
| Health insurance premium | PhilHealth, https://www.philhealth.gov.ph | RA 7875 as amended by RA 11223 (Universal Health Care Act) | PhilHealth Circular number, premium rate, floor/ceiling, effectivity |
| Home Development Mutual Fund | Pag-IBIG Fund, https://www.pagibigfund.gov.ph | RA 9679 (HDMF Law of 2009) | HDMF Circular number, rates, maximum fund salary, effectivity |
| Withholding tax on compensation | Bureau of Internal Revenue, https://www.bir.gov.ph | NIRC as amended by RA 10963 (TRAIN) and later laws (e.g. RA 11976, Ease of Paying Taxes) | Revenue Regulation number + withholding table annex, de minimis and non-taxable benefit rules |
| 13th month pay | DOLE, https://www.dole.gov.ph | PD 851 and its Revised Guidelines | Guideline issuance. The tax-exempt portion is from NIRC/BIR, so cite both. |
| Minimum wage | National Wages and Productivity Commission, https://nwpc.dole.gov.ph | RA 6727 (Wage Rationalization Act) | Regional Wage Order number, region, sector, effectivity |
| Overtime, night shift differential, rest day, holiday pay, service incentive leave | DOLE Bureau of Working Conditions, https://bwc.dole.gov.ph | Labor Code (PD 442), Book III. Articles were renumbered (DOLE Dept. Advisory No. 1, s. 2015), so cite the article **and** the numbering used. | DOLE *Handbook on Workers' Statutory Monetary Benefits* (latest edition) |
| Holiday list and type (regular vs special non-working) | Office of the President proclamations, https://www.officialgazette.gov.ph | Annual and ad hoc proclamations | Proclamation number. This feeds the PH holiday calendar. |
| Statutory leaves with pay effects | DOLE, SSS (benefit portions), Official Gazette | e.g. RA 11210 (maternity), RA 8187 (paternity), RA 11861 (solo parent), RA 9262 (VAWC), RA 9710 (special leave for women) | Law + IRR. The SSS maternity benefit and the employer's salary differential are separate lines. |
| Payroll personal data | National Privacy Commission, https://privacy.gov.ph | RA 10173 (Data Privacy Act) | NPC circulars on security of personal data |

Record the URL and retrieval date, quote the section relied on, and get the
user's confirmation before a rule version moves to `verified`.

## PH rule keys (map onto the generic rule model)

| `rule_key` | `category` | Notes |
|---|---|---|
| `PH.SSS.CONTRIBUTION` | social_insurance + employer_contribution | Employee and employer shares are separate lines, and any EC component is employer-only. Store the schedule exactly as published (salary-credit ranges). |
| `PH.PHILHEALTH.PREMIUM` | healthcare | Employee/employer split exactly as the circular states |
| `PH.HDMF.CONTRIBUTION` | pension (provident savings) | Employee/employer shares, maximum fund salary as published |
| `PH.BIR.WITHHOLDING` | income_tax | One table per payroll frequency, as the RR annex presents it. Annualization and year-end adjustment exactly as the RR states. |
| `PH.BIR.NON_TAXABLE` | income_tax | 13th month / other benefits exemption and de minimis items, each with a citation |
| `PH.NWPC.MINIMUM_WAGE` | minimum_wage | `jurisdiction_code` = wage region, plus sector. Validates rates and doesn't calculate them. |
| `PH.DOLE.PREMIUM_PAY` | overtime, holiday_pay, rest_day_pay, night_work | Multipliers and combination order (e.g. overtime on a holiday that is also a rest day) as the Handbook computes them, with one golden test per combination |
| `PH.DOLE.THIRTEENTH_MONTH` | statutory_deduction / earning rule | Basis and proration as the PD 851 guidelines define |
| `PH.DOLE.SERVICE_INCENTIVE_LEAVE` | leave | Eligibility and conversion rules from the Labor Code and Handbook |

`applies_by` (pay date vs period vs applicable month) is set per rule from its issuance and covered by a boundary test.
Splitting monthly contributions across semi-monthly runs is **company policy**, not law. It is configured per
legal entity and must not be encoded as a statutory rule.

## PH reports and filings (generated from finalized lines only)

SSS, PhilHealth, and HDMF contribution and remittance reports; BIR 1601-C (monthly),
1604-C with alphalist (annual), and BIR Form 2316 (per employee). Before building any export,
confirm the current file layout and submission channel on the agency's official site.

## Before calling a PH feature done

- [ ] Every value comes from a `PH.*` rule version with source URL, issuance, effectivity, and `verified` status.
- [ ] No PH literal (rate, agency name, `'PH'` branch, peso amount) outside `Localizations/PH/`.
- [ ] The PH test suite runs on its own with fictional fixture values: bracket edges and effectivity boundaries.
- [ ] The user has confirmed the issuances. Don't claim compliance beyond what was cited and verified.

This skill guides implementation. It is not legal or tax advice. Statutory configuration should be
reviewed by the customer's Philippine payroll/tax adviser.
