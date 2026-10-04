# Recruitment Phase 8: Reporting & Analytics

## 1. Architecture
Recruitment reports use the **existing Core HR report framework**. There is no second engine.

| Piece | What |
|---|---|
| `app/Services/Reports/Recruitment/RecruitmentReport.php` | Base class: category `recruitment`, shared filters, funnel helpers |
| `app/Services/Reports/Concerns/FiltersRecruitment.php` | Filters, period handling, rates, day statistics (the counterpart of `FiltersEmployees`) |
| `app/Services/Reports/Recruitment/*Report.php` | 14 reports, each a normal `Report` subclass |
| `ReportRegistry` | New category `recruitment` (`report.recruitment`) and the 14 classes |

Everything else is reused unchanged:
- the generated routes (`reports.{key}.show|excel|pdf|print`);
- `ReportController`, `ReportFilterRequest`, the `app/Reports/Show` page and the catalog;
- Excel export (`ReportExport`), PDF and print (`reports.document`).

The sidebar's Reports group gained a "Recruitment" section, gated on `report.recruitment`.

## 2. Access
| Permission | Who | Grants |
|---|---|---|
| `report.recruitment` (new) | superadmin, hr_director, hr_manager, hr_staff | open recruitment reports |
| `report.export` (existing) | same as today | Excel, PDF, print |

- Supervisors (including hiring managers), payroll and employees get nothing. Their recruitment pages are unaffected.
- **Scope:** like every Core HR report category, scope is organisation-wide for permission holders and narrowed by the department filter. The framework has no per-user organisational scoping, and none was invented.
- **Rollout:** migration `2026_10_11_000001_add_recruitment_report_permission` is additive and mirrored in `RolesAndPermissionsSeeder`.

## 3. Filters
| Filter | Meaning |
|---|---|
| From / To | Inclusive whole days. Implemented as `col >= from AND col < to + 1 day`, the same meaning as `whereDate` but able to use an index |
| Department (+ sub-departments) | The **vacancy's** department, using `DepartmentTree` as in Core HR reports |
| Vacancy, Hiring manager | On the vacancy |
| Source | On the application; "Not specified" = no source recorded |
| Vacancy status | Vacancies report only |

Each report lists which date column it uses in its notes. Invalid filters return validation errors.

## 4. Reports and metric definitions
All application counts are **distinct applications**. Rates show "—" when the denominator is 0, never a misleading 0%.

1. **Recruitment Funnel**, in three separate sections:
   - **Cohort:** applications received in the period, and whether each ever reached Screening, Shortlisted, Interview, Assessment, Evaluation, Selected, Offer issued, Offer accepted, Hired. Evidence comes from stage history, selections, offers and conversions. Shows % of applied and % of the previous step; stages can be skipped, so the values are not capped.
   - **Activity:** events in the period, each by its own timestamp.
   - **Current pipeline:** where open applications are today, ignoring the period.
2. **Vacancies & Requisitions:**
   - vacancies that existed in the period, with openings, reserved (selected), applications (all and in the period), accepted offers and hires;
   - requisition events by their own timestamps;
   - approved requisitions with no vacancy.
3. **Vacancy Aging:**
   - open and on-hold vacancies today, aged from `opened_at`;
   - 0–30, 31–60, 61–90 and 90+ day buckets, statistics, and vacancies past their closing date.
4. **Application Sources:** cohort by source, with shortlisted, interviewed, offered, accepted, hired and hire rate.
5. **Screening:**
   - results by `screened_on` and pass rate;
   - failure reasons and results by vacancy;
   - applications awaiting screening today.
6. **Interviews:** by (latest) `starts_at`.
   - Completion rate = completed ÷ (completed + cancelled).
   - Also shows rescheduled interviews, distinct applications interviewed, and breakdowns by type, vacancy and month.
   - Panelist workload (internal employees).
7. **Assessments & Evaluations:**
   - assessments created (by `created_at`), and completed by type (by `completed_at`) with pass/fail;
   - scorecard completion = submitted ÷ panelists on interviews completed in the period;
   - no scores, ratings, recommendations or comments.
8. **Selection:**
   - HR and automatic decisions by `decided_at`, with reversals;
   - vacancies with "selected now" (each application's latest decision) against openings.
9. **Offers:**
   - lifecycle events by their own timestamps;
   - approval rate = approved ÷ (approved + rejected);
   - outcomes of offers issued in the period, days from issue to response, and results by vacancy;
   - no salary or terms.
10. **Time to Fill:**
    - vacancy `opened_at` → offer accepted (`responded_at`), one row per accepted offer;
    - secondary measure: requisition `approved_at` → offer accepted.
    - Vacancy status "filled" (a manual action) and `filled_count` (reserved at selection) are **not** used as end events.
11. **Time to Hire:**
    - `applied_at` → offer accepted, plus `applied_at` → conversion;
    - by department and source; aggregate only.
12. **Hiring Manager & HR Workload:**
    - hiring managers, from `vacancies.hiring_manager_id`;
    - HR users, from the actor recorded on each action (`created_by`, `acted_by`, `screened_by`, interview `created_by`, offer `created_by` / `issued_by`, `converted_by`).
13. **Careers Portal:**
    - `recruitment_portal_events` by `occurred_at`;
    - online share (applications with no HR `created_by`), by vacancy;
    - vacancies publicly listed today.
14. **Hiring Conversion:**
    - offers accepted in the period → converted, awaiting, rate, days to convert;
    - conversions in the period by type, user account, department and vacancy.

## 5. Privacy
- No report or export shows applicant names, emails, phones, addresses, application or offer numbers, documents, notes, remarks, scores, ratings, recommendations or salary.
- Names shown are internal staff only: hiring managers, panelists and HR users.
- Per-row reports list vacancies, never candidates. Time to hire is aggregate only.
- A test checks every report's page props, summary and print output against the fixture candidate's details.

## 6. Read-only guarantees
- Every report query is a plain `SELECT`. Nothing locks, writes or uses a transaction.
- **SQLite test:** no statement other than SELECT for any report.
- **MariaDB** (`tests/Concurrency/RecruitmentReportingConcurrencyTest.php`):
  1. six concurrent runs of all 14 reports leave the `CHECKSUM TABLE` of every source table unchanged, produce identical output, and match a later run on its own;
  2. reports racing six applications, a conversion and an offer response don't block or fail those writes.
- Each statement reads the latest committed data. A report running during heavy writes can mix data from just before and just after a commit; a report never takes a single snapshot.

## 7. Database changes
- `2026_10_11_000001`: the `report.recruitment` permission.
- `2026_10_11_000002`: indexes for the period filters on tables that grow with every application or event:
  - `applications(applied_at)`
  - `application_stage_histories(acted_at, action)`
  - `job_offers(responded_at)`
  - `recruitment_portal_events(occurred_at, event)`

No reporting tables and no data changes.

## 8. Known limitations (schema, not invented)
- **No recruiter ownership field:** no recruiter metrics beyond recorded actions.
- **No page views or visits:** no careers traffic or view-to-apply rates.
- **No vacancy status history:** only the current status and open/close timestamps.
- **Interviews keep only their latest time:** history is in `interview_reschedules`. No attendance or no-show data.
- **Calendar days:** time-to-fill includes time on hold, and nothing is adjusted for working days or holidays.
- **No charts:** the framework renders tables; charts would be a framework-wide change.

## 9. Tests
- `tests/Feature/Reports/RecruitmentReportsTest.php`, 16 tests:
  - access by role and the export permission;
  - catalog;
  - filter validation;
  - funnel distinctness and its three views;
  - inclusive date boundaries;
  - department tree scope;
  - sources;
  - stage reports;
  - offers, time to fill and hire, conversion;
  - aging and workload;
  - careers;
  - privacy;
  - Excel export;
  - read-only.
- `tests/Concurrency/RecruitmentReportingConcurrencyTest.php`, 2 MariaDB tests.
