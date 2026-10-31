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

## Part A: payroll pre-commit verification (2026-10-30)
- `tests/Unit/Payroll`: OK (22 tests, 38 assertions). `tests/Feature/Payroll`: OK (80 tests, 582 assertions).
- Pint passes on all payroll files; `npm run build` OK; payroll migrations still Pending. Committed as `a498209`.

## Attendance (2026-10-31, after all edits)
- `php -d memory_limit=1G vendor/bin/phpunit tests/Unit/Attendance`: OK (6 tests, 9 assertions).
- `php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Attendance`: OK (50 tests, 447 assertions):
  `AttendanceEngineTest` (14), `DeviceIngestionTest` (12), `DeviceProtocolTest` (7), `AttendanceAccessTest` (12),
  `AttendancePayrollIntegrationTest` (3), `AttendanceReportsTest` (2). Payroll suite still OK (80) with the attendance time provider bound.
- Full suite `php -d memory_limit=1G vendor/bin/phpunit`: **Tests: 703, Assertions: 7726, Errors: 6.** The 6 errors are the
  pre-existing legacy `MaintenanceLeavesControllerTest` errors (same as baseline). **No new failures.**
- `php artisan migrate --pretend`: valid MySQL DDL for all 14 pending migrations (40 tables); nothing executed.
- `npm run build` OK; pint passes on all attendance files.
- Device tests use the simulator and `Http::fake`; **no hardware test was run** (no device available).

## Attendance legacy replacement and pre-commit audit (2026-10-31)
All runs: `php -d memory_limit=1G vendor/bin/phpunit <path>` on in-memory SQLite.
- `tests/Unit/Attendance`: OK (6 tests, 9 assertions).
- `tests/Feature/Attendance`: OK (55 tests, 513 assertions), including new `LegacyAttendanceTransitionTest` (4: obsolete routes gone/redirect, navigation, self-service reads engine results, dry-run/idempotent/read-only legacy import), connector entity/location scope, payroll manual-inputs group ignores attendance.
- `tests/Unit/Payroll`: OK (22, 38). `tests/Feature/Payroll`: OK (80, 582).
- `tests/Feature/Leave`: OK (92, 748). `tests/Feature/People`: OK (33, 218). `tests/Feature/SelfService`: OK (32, 550; My Attendance and dashboard now on the new tables). `tests/Feature/Reports`: OK (117, 1877; Attendance Log on `attendance_events`, supervisor scoping, summary report removed).
- `tests/Feature/Time/TimeLogsTest.php` deleted with the Time Logs page (its behavior is covered by the Attendance Log report and transition tests); the folder has no other tests.
- Full suite: **Tests: 694, Assertions: 7671, Errors: 6, PHPUnit Deprecations: 6.** The 6 errors are the known baseline `MaintenanceLeavesControllerTest` (index, create, store, edit, update, delete). No other failures. (703 → 694: removed legacy tests, added new ones.)
- Migrations on scratch MySQL `general_hrms_attendance_scratch`: full migrate OK, rollback of the 15 new migrations OK, re-migrate OK after seeding roles. Dev DB `general_hrms`: 15 pending, untouched.
- `npm run build`: OK (only the existing chunk-size warning). Pint: run on attendance-related files; unrelated churn in `routes/web.php` reverted (only the one-line removal kept).
- No hardware test was run.

## Attendance: complete legacy removal (2026-10-31, final)
- `tests/Unit/Attendance`: OK (6, 9). `tests/Feature/Attendance`: OK (56, 1638), incl. `LegacyAttendanceRemovalTest` (routes gone, legacy tables/permissions absent after migrations, no application code mentions the legacy models/tables/command).
- `tests/Unit/Payroll`: OK (22, 38). `tests/Feature/Payroll`: OK (80, 582).
- `tests/Feature/Leave`: OK (92, 748). `tests/Feature/People`: OK (33, 218). `tests/Feature/SelfService`: OK (32, 550). `tests/Feature/Reports`: OK (117, 1877).
- No test uses the legacy tables or models (the only mentions are the absence assertions in `LegacyAttendanceRemovalTest`).
- Full suite: **Tests: 695, Assertions: 8796, Errors: 6, PHPUnit Deprecations: 6.** The 6 errors are the baseline `MaintenanceLeavesControllerTest`. No other failures.
- Scratch MySQL: `general_hrms_attendance_scratch` migrate (15) / rollback (15) / migrate (15) OK; new `general_hrms_cleanup_scratch` full migrate from empty (101) OK, roles seeder and `AcmeDemoSeeder` OK; no legacy tables in the final schema. Dev DB `general_hrms`: 15 pending, untouched.
- `npm run build`: OK. Pint: attendance-related files pass; five legacy files edited only to remove biometric/legacy lines (`EmployeeResource`, `UpdateEmployeeJobDetailsFormRequest`, `LeaveApplicationFormRequest`, `LeaveService`, `routes/web.php`) already failed Pint before the edits and were not reformatted.

## Hikvision DS-K1T321MFX physical test and automatic sync (2026-10-31, local HRMS on general_hrms)
- Physical device (firmware V4.61.0 build 260828): connection test, device info (XML), clock, AcsEvent search, pagination, timestamp/offset preservation, duplicate prevention, unmapped retention and later mapping all verified through the HRMS. Details: `attendance/HIKVISION_DS_K1T321.md` §6.
- Automatic sync via `php artisan schedule:work`: every-minute runs from 13:15; scan at device time 13:27:37 stored at 13:28:00 by the scheduled run (23 s), no button clicked; idle runs read ~11-minute windows and retrieve 0.
- Logs and sync history scanned for the device password, digest and Authorization headers: none found.
- `tests/Feature/Attendance` + `tests/Unit/Attendance`: OK (68 tests, 1672 assertions), incl. new cursor/overlap, failed-pull, device-lock, explicit-window, lookback and XML device-info tests.

## Hikvision alertStream (2026-10-31, physical DS-K1T321MFX)
- alertStream: digest + multipart JSON verified; real scan 15:44:16 (serial 646) stored via stream at 15:44:17 (≈1 s); next poll: 1 retrieved, 0 new, 1 duplicate; one row.
- Listener ran ~2 h: auto-reconnect after stream interruption (curl 56), idle timeouts, transient HTTP 404, and a Mac-sleep stall; SIGTERM stop in ~2 s.
- Bug found and fixed: a long-running listener's stale device instance could write an older polling cursor back (`touchDevice` now merges into the stored cursor); regression test added.
- `tests/Feature/Attendance` + `tests/Unit/Attendance`: OK (77 tests, 1705 assertions), incl. `DeviceStreamTest` (6) and `MultipartStreamParserTest` (3).
- Not yet run on hardware: device network disconnect/reconnect with a scan during the outage.

## Hikvision HTTP host push (2026-10-31, physical DS-K1T321MFX)
- Slot 1 configured (approved), device posted multipart JSON from 192.168.88.215; it sent its stored log, repeating serial 25 ~1/s despite `200 OK` (acknowledgement not accepted). One historical record stored (event 9). Receiver stopped, slot 1 restored to the original (verified), token revoked (it appeared in `php artisan serve` console output).
- No live scan received via push: **push not verified**.
- Code: push accepted for polled devices, source-IP check, JSON/XML/multipart parsing, history guard, request log (no values). `tests/Feature/Attendance` + `tests/Unit/Attendance`: OK (78 tests, 1722 assertions).

## Laravel HTTP Listening receiver on hardware (2026-10-31, scratch DB `general_hrms_cleanup_scratch`)
- Live check-in/check-out/check-in/break-out (serials 684, 687, 690, 693) stored once each, 200 actually sent (55-110 ms); match alertStream.
- Outage + duplicate: event 696 polled into the scratch DB while the receiver was down; after restart the device delivered it, Laravel answered `duplicate` + 200; one row.
- Backlog/history filter not triggered on hardware this round (automated tests cover it).
- `tests/Feature/Attendance` + `tests/Unit/Attendance`: OK (80 tests, 1734 assertions), incl. 500-on-failure and ISAPI acknowledgement tests.

## Push-primary sync (2026-10-31)
- New `PushPrimarySyncTest` (4): push-healthy skip + hourly reconciliation, silent/pull-only devices pulled every run, `--force`, page-by-page ingestion with failure keeping the cursor and recovery without duplicates, heartbeat marking + break statuses.
- `tests/Feature/Attendance` + `tests/Unit/Attendance` + payroll suites: OK (186 tests, 2389 assertions).
- Benchmark (temporary, not committed; in-memory SQLite): 3,000 new events 9,008 → 6,108 queries, 9.7 → 1.8 MB peak; re-read 107 queries.
- Hardware: real heartbeats set `push_seen_at`; scheduled run skipped the device. Slot 1 restored afterwards (verified identical).
