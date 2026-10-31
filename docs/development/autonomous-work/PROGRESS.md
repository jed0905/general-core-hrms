# Progress

## Phase 0: Audit & architecture (complete)
- Audit of routes, models, migrations, Time/Leave/Attendance, permissions, tests, legacy payroll. Findings: `docs/development/payroll/ARCHITECTURE.md` §2.
- Deliverables: `docs/development/payroll/ARCHITECTURE.md`, `IMPLEMENTATION_PLAN.md`, autonomous-work files.
- Baseline: see TEST_RESULTS.md.
- Pre-existing uncommitted changes (`.DS_Store`, `CLAUDE.md`, `composer.json`, `composer.lock`, `.claude/`, `.mcp.json`, `boost.json`, `docs/development/international-payroll-architecture.md`) left untouched.

## Phase 1: Foundation (schema, models, permissions: complete; config UI: see Phase 1b)
- Migrations `2026_10_30_000001` … `000009` (reference, organization, calendars, compensation, statutory rules, inputs, runs, disbursements/audit, permissions).
- Models: Currency, ExchangeRate, LegalEntity, PayGroup, EmploymentAssignment, EmployeeTaxResidency, EmployeeStatutoryProfile, PayGroupCalendar, PayrollPeriod, PayComponent, PayComponentTreatment, SalaryStructure, SalaryStructureGrade, EmployeeCompensation, EmployeePayComponent, StatutoryRuleVersion/Bracket/Parameter, PayrollTimeInput, PayrollAdjustment, PayrollRun, PayrollRunEmployee, PayrollRunLine, PayrollRunException, PayrollRunEvent, EmployeeBankAccount, PayrollDisbursement, PayrollAuditLog. `User::payrollEntities()` added.
- Permissions: migration `000009` + `RolesAndPermissionsSeeder` (`PAYROLL_PERMISSIONS`, `PAYROLL_GRANTS`).

## Phase 2: Global engine (complete)
- `app/Services/Payroll/{Support,Contracts,Localization,Calculation,Rules}`; `config/payroll.php`; bindings in `AppServiceProvider::register()`.
- Tests: `tests/Unit/Payroll/{DecimalTest,RateConverterTest}`, `tests/Feature/Payroll/PayrollCalculationTest` (fictional `XX` localization in `tests/Fixtures/Payroll`).

## Phase 3: Time & leave integration (complete)
- `Inputs/ApprovedLeaveProvider` (approved leave only), `Inputs/ApprovedTimeInputProvider` (approved payroll time inputs), `Inputs/WorkingDayCounter` (Time module `resolveDay`).
- Tests: `tests/Feature/Payroll/PayrollTimeAndLeaveIntegrationTest`.

## Phase 5 (core service done early, needed by engine tests)
- `PayrollRunService`, `DisbursementService`; tests `PayrollRunLifecycleTest`.

## Phase 4: Philippines localization (complete to the extent verifiable; values pending: BLOCKERS B3)
- `app/Services/Payroll/Localizations/PH/PhilippinesLocalization.php`: SSS (bracket lookup, EE/ER/EC lines), PhilHealth (rate with floor/ceiling, employee share), Pag-IBIG (bracket rates, max fund salary, voluntary extra), BIR withholding (progressive table per frequency variant), 13th-month/benefits ceiling, DOLE premium categories (compounded multipliers), regional minimum wage warning, minimum wage earner exclusion, monthly contribution schedules (company policy).
- Official sources located and recorded (unverified): `docs/development/payroll/PH_LOCALIZATION.md` §5.
- Tests: `tests/Feature/Payroll/Localizations/PH/` (fictional values only).
- Core refinement found while testing: rule versions are selected by their own `applies_by` (D16); earnings rounded before bases (D17).

## Phase 5: Processing & approval (complete)
- `PayrollRunService` (create, include/exclude, calculate, review, approve, return, cancel, finalize, reverse, supplemental), `PayrollRunPolicy`, `PayrollRunController`, pages `Runs/Index`, `Runs/Show`.
- Configuration/input layer: `PayrollSetupService`, `PayrollCalendarGenerator`, `EmployeePayrollService`, `StatutoryRuleService`, `PayrollInputService`; controllers `PayrollSetupController`, `PayrollEmployeeController`, `StatutoryRuleController`, `PayrollInputController`; 25 FormRequests in `app/Http/Requests/Payroll`; policies in `app/Policies/Payroll`; gates `viewPayrollEmployees`, `accessPayrollInputs`.
- Routes appended to `routes/Payroll/web.php` (separate group, every route has `can:`).

## Phase 6: Payslips & disbursement (complete)
- `PayslipService` (stored lines only, PDF via dompdf `resources/views/payroll/payslip.blade.php`), `PayslipController`, `PayslipPolicy` (own + finalized only), pages `MyPayslips/Index`, `Payslips/Show`.
- `DisbursementService`: disbursements opened at finalization (encrypted destination snapshot), status tracking, CSV and ISO 20022 pain.001.001.03 export (file only).

## Phase 7: Reports (complete; agency file layouts blocked: B4)
- `app/Services/Reports/Payroll/*` registered in `ReportRegistry` category `payroll` (`report.payroll`): Summary, Register, Earnings & Deductions, Statutory Contributions, Tax Withheld, Payroll Cost, Reconciliation. Scoped by legal entity; finalized only; per currency.

## Phase 8: Hardening & documentation (complete)
- Frontend: pages under `resources/js/pages/app/Payroll/{Dashboard,Runs,Inputs,Employees,StatutoryRules,Setup,MyPayslips,Payslips}`, `utils/payroll.js`, `utils/money.js`, `mixins/payrollPage.js`; sidebar entries (payroll group + My Payslips); `SidebarLayout::hasPermission` accepts an array ("any of").
- `HandleInertiaRequests` shares `flash.success` (D10/D10a).
- Statutory profile column renamed `attributes` → `fields` before any deployment (collided with Eloquent's internal `$attributes`).
- Entity user scope assignment UI + route `payroll.setup.legal-entities.users`.
- Docs: `docs/development/payroll/OPERATIONS.md`.

## Files modified outside new payroll files
`app/Http/Middleware/HandleInertiaRequests.php` (share success flash), `app/Models/Employee.php` (+`payrollAssignments()`), `app/Models/User.php` (+`payrollEntities()`; pint removed an unused import), `app/Providers/AppServiceProvider.php` (bindings), `app/Providers/AuthServiceProvider.php` (policies + 2 gates), `app/Services/Reports/ReportRegistry.php` (payroll category), `database/seeders/RolesAndPermissionsSeeder.php` (payroll permissions/defaults), `resources/js/layouts/SidebarLayout.vue` (menu + array permissions), `routes/Payroll/web.php` (new group appended; pint reformatted whitespace in the legacy group, no behavior change).

## Next
- Human: enter and verify PH rule versions (B3); decide on agency file formats (B4); run `php artisan migrate` on the dev database when ready (not run by the agent).
- `.claude/skills/hrms-payroll/SKILL.md` "Current state" still describes payroll as not built; update it when convenient.

Note: during the session the repository owner committed the pre-existing changes (`956a454 updated claude skills`, author JED). The agent made no commits; all payroll work is uncommitted in the working tree.

---
# Attendance module (started 2026-10-30)

## Part A: payroll committed
- Verified before commit: `tests/Unit/Payroll` OK (22 tests, 38 assertions); `tests/Feature/Payroll` OK (80 tests, 582 assertions); pint passes on all payroll files (pre-existing unrelated models fail pint and were not touched); `npm run build` OK; the 9 payroll migrations still **Pending** on `general_hrms` (never run by the agent).
- Commit **`a498209`** `feat(payroll): implement global payroll core with Philippines localization framework` (162 files, staged by explicit path; no `.env`, build output, DB files). Not pushed. Working tree clean afterwards.

## Phase 0: attendance audit (complete)
- Found prior Hikvision work on branch `feature/hikvision-api` (`3203d23`); see `docs/development/attendance/ARCHITECTURE.md` §1.

## Attendance phases 1–9 (complete; hardware verification pending)
- **Phase 1 foundation**: migrations `2026_10_31_000001`…`000005` (devices, connectors, sync runs, enrollments, raw events, policies, roster, days, adjustments, period summaries, audit log, holiday jurisdiction + `pay_groups.time_source`, permissions); models; `AttendanceScope`; permissions in migration + seeder (`ATTENDANCE_PERMISSIONS/GRANTS`).
- **Phase 2 devices**: `Devices/Contracts/DeviceAdapter`, `DeviceAdapterRegistry`, `config/attendance.php`, `AttendanceDeviceService`, `DeviceSyncService` (retries, lock, overlap window, status), device pages.
- **Phase 3 simulator + ingestion**: `SimulatorAdapter`, `ManualAdapter`, `EventIngestionService` (idempotent, unmatched/invalid kept, remap), `AttendanceEventEntryService` (manual, CSV, legacy table), `attendance:sync-devices` (scheduled every 5 min).
- **Phase 4 Hikvision**: `HikvisionIsapiAdapter` (pull with pagination, device info, clock drift, push parsing), `HikvisionPushController`, `HIKVISION_DS_K1T321.md`.
- **Phase 5 ZKTeco**: `ZktecoAdmsAdapter` (PUSH/ADMS ATTLOG, handshake without templates), `ZktecoPushController`, `ZKTECO_INTEGRATION.md`; TCP/SDK not implemented.
- **Phase 6 engine**: `Engine/DayContextResolver`, `DayContext`, `AttendanceCalculator`, `AttendanceRecalculator`; roster, holidays by jurisdiction, approved leave, adjustments (`AttendanceAdjustmentService`, `AttendanceAdjustmentPolicy`).
- **Phase 7 UI & reports**: 11 pages under `resources/js/pages/app/Attendance`, `components/Attendance/AdjustmentDialog.vue`, `utils/attendance.js`, `mixins/attendancePage.js`, sidebar group; reports `attendance-daily`, `attendance-totals`, `attendance-device-sync`; holiday form gains country/region fields (Time module, additive).
- **Phase 8 payroll**: `AttendancePeriodService`, `AttendanceTimeInputProvider` (bound as payroll's `TimeInputProvider`; manual groups unchanged), contracts `MapsAttendanceToPremiums` + `ValidatesTimeInputs`, event `PayrollRunFinalized` + listener `LockConsumedAttendanceSummaries`; PH localization maps attendance buckets.
- **Phase 9 hardening**: connector (`attendance:connector`, HMAC + nonce, spool, no re-spooling), `ConnectorEventController`, UTC instant cast (`App\Casts\UtcDateTime`), docs in `docs/development/attendance/*`.
- Shared files touched (additive): `AppServiceProvider`, `AuthServiceProvider` (policy + 3 gates), `EventServiceProvider`, `RouteServiceProvider` (2 route files), `Console/Kernel` (schedule), `ReportRegistry` (3 reports + catalog honors per-report permission), `SidebarLayout.vue`, `Holiday` model + holiday requests/service/page, `PayGroup` model/request + payroll setup page, `PayrollCalculator` (generic `ValidatesTimeInputs` hook), `PayrollRunService` (dispatches `PayrollRunFinalized`), PH localization (mapping method), XX test fixture.
- Pint was accidentally run on all of `app/Events`; the three unrelated files it reformatted were restored with `git restore` (no other changes in them).
- Not committed (only the payroll commit was requested).

## Next
- Hardware verification on the customer's DS-K1T321 (checklist in `HIKVISION_DS_K1T321.md` §4) and on a ZKTeco PUSH device.
- Run `php artisan migrate` (15 pending migrations: payroll then attendance) on the dev DB when approved, then `php artisan event:cache`.
- ~~Decide whether Time Logs / My Attendance should read the new tables (BLOCKER A-B1).~~ Done 2026-10-31 (legacy replacement below).

## Attendance: legacy replacement and pre-commit audit (2026-10-31)
- Decision: the attendance module is the only attendance architecture (D A15).
- Replaced: Time Logs → Attendance Raw Logs (old URL redirects); My Attendance → `attendance.my.*` (calculated days, punches, corrections, CSV); employee dashboard card and the Attendance Log report now read `attendance_events`/`attendance_days`; Raw Attendance Summary report removed (Daily Attendance / Attendance Totals); sidebar updated.
- Removed: legacy services/controllers/requests/pages for Time Logs and My Attendance, the unrouted DTR suite, the broken scheduled `CheckTardinessJob` + `TardinessMail`, models `EmployeeAttendanceLog`/`EmployeeBiometricId`, the web "import legacy" action, a dangling route import, unused permissions `attendance.update/delete/reprocess` (migration `2026_10_31_000006`).
- Added: `App\Services\Attendance\EmployeeAttendanceService` (shared by HR history, self-service, dashboard), `AttendanceHistoryRequest`, `LegacyAttendanceImporter` + `attendance:import-legacy-logs` (dry run by default, timezone required, idempotent, legacy table read-only), connector entity/location scope check, `tests/Concerns/RecordsAttendanceEvents`, `LegacyAttendanceTransitionTest`, payroll manual-inputs test; demo seeder now seeds simulated devices, mappings and events through the pipeline.
- Legacy table was first kept as a read-only archive (superseded by the complete removal below).
- All migrations verified on a scratch MySQL database `general_hrms_attendance_scratch` (migrate, rollback of the 15 new ones, seed roles, re-migrate). The dev DB `general_hrms` still has the 15 migrations pending.
- Audit summary: `attendance/PRE_COMMIT_AUDIT.md`. Not committed.

## Attendance: complete legacy removal (2026-10-31, final)
- Owner confirmed `general_hrms` holds only test/demo data and authorized removing the legacy attendance system.
- Removed: `attendance:import-legacy-logs` + `LegacyAttendanceImporter`, the `legacy` event source, the old-URL redirects, biometric-ID fields of the unrouted legacy employee edit (`JobForm.vue`, three resources, `UpdateEmployeeJobDetailsFormRequest`), dangling `LeaveService` import, archive/cutover docs (`LEGACY_TRANSITION.md` → `LEGACY_REMOVAL.md` record).
- `LeaveApplicationFormRequest` (unused) wellness rule now reads `attendance_events`.
- Migration `2026_10_31_000006_remove_legacy_attendance` drops `employee_attendance_logs` / `employee_biometric_ids` and removes the unused permissions (replaces the uncommitted permissions-only migration).
- CLAUDE.md attendance section updated (it described the removed tables as current).
- Tests: `LegacyAttendanceRemovalTest` (routes gone, tables/permissions gone, no code references legacy names).
- Verified on scratch MySQL `general_hrms_attendance_scratch` (migrate/rollback/migrate) and new `general_hrms_cleanup_scratch` (from empty + roles + demo seeder). Dev DB untouched. Not committed.

