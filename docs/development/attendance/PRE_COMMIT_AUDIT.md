# Attendance Pre-Commit Audit (2026-10-31, final)

Branch `feature/claude-agent`. Payroll is committed (`a498209`); `956a454` ("updated claude skills")
is untouched. Attendance work and the legacy removal are **uncommitted**; nothing is pushed. Branch
`feature/hikvision-api` is kept as a reference (do not deploy it: it writes to a dropped table).

## 1. Legacy components removed

Complete list in `LEGACY_REMOVAL.md`: legacy tables and models, Time Logs, the old My Attendance,
the raw-punch summary report, the old DTR suite, `CheckTardinessJob` and its mail, the legacy import
(web action, command, `legacy` source), unused permissions `attendance.update/delete/reprocess`, the
biometric-ID field of the unrouted legacy employee edit, obsolete tests and fixtures, and old URLs
(no redirects are kept: the system is new).

## 2. Remaining references to the legacy system

| Where | Why it stays |
|---|---|
| `database/migrations/2025_05_19_000018_create_employee_attendance_logs_table.php`, `2026_01_07_082554_create_employee_biometric_ids_table.php` | Already ran on existing databases (`general_hrms`, possibly others); editing or deleting them would fork the migration history. `2026_10_31_000006` drops the tables. |
| `database/migrations/2026_10_31_000006_remove_legacy_attendance.php` | The drop itself (and a `down()` that recreates the empty tables). |
| Docs (`LEGACY_REMOVAL.md`, this file, architecture/progress notes, branch `feature/hikvision-api`) | Historical record only. |

`LegacyAttendanceRemovalTest::no_application_code_refers_to_the_legacy_attendance_system` scans
`app`, `routes`, `resources/js`, `resources/views`, `database/seeders`, `database/factories` and
`config` for the legacy model and table names and the import command; it passes.

## 3. Attendance architecture (verified independently of the legacy code)

| Workflow | Component | Tests |
|---|---|---|
| Multiple devices, entity/location | `AttendanceDevice`, `AttendanceDeviceService` | `AttendanceAccessTest` |
| Hikvision (ISAPI pull, paging, push) | `HikvisionIsapiAdapter`, `HikvisionPushController` | `DeviceProtocolTest` (HTTP fakes) |
| ZKTeco HTTP PUSH (ATTLOG) | `ZktecoAdmsAdapter`, `ZktecoPushController` | `DeviceProtocolTest` |
| Local connector | `attendance:connector`, `ConnectorEventController` (HMAC, nonce, entity/location scope) | `AttendanceAccessTest`, `DeviceProtocolTest` |
| Simulator | `SimulatorAdapter` | `DeviceIngestionTest` |
| Manual entry, CSV import | `AttendanceEventEntryService` | `AttendanceAccessTest` |
| Central ingestion, duplicates, raw history | `EventIngestionService`, unique `dedupe_key`, append-only `AttendanceEvent` | `DeviceIngestionTest` |
| Biometric mappings | `BiometricEnrollmentService` (per device, one active per device user, remap of unmatched events) | `AttendanceAccessTest`, `DeviceIngestionTest` |
| Calculations, shifts, roster, leave, holidays | `DayContextResolver`, `AttendanceCalculator`, `AttendanceRecalculator` | `AttendanceEngineTest` |
| Adjustments and independent approval | `AttendanceAdjustmentService`, `AttendanceAdjustmentPolicy` | `AttendanceAccessTest`, `AttendanceEngineTest` |
| Reports | `attendance-log`, `attendance-daily`, `attendance-totals`, `attendance-device-sync` | `AttendanceReportsTest`, `CoreHrReportsTest` |
| Self-service, dashboard | `MyAttendanceController`, `EmployeeAttendanceService`, `EmployeeDashboardService` | `LegacyAttendanceRemovalTest`, `EmployeeSelfServiceTest`, `EmployeeDashboardTest` |
| Payroll | `AttendancePeriodService`, `AttendanceTimeInputProvider`, `PayrollRunFinalized` listener | `AttendancePayrollIntegrationTest` (incl. manual-inputs default) |

Security kept: encrypted device credentials, card numbers and payloads; nothing secret sent to pages;
no templates or images collected (ZKTeco handshake requests ATTLOG only); connector entity/location
isolation; audit logs for device, connector, enrollment, adjustment, policy, roster and summary
actions; permission-first authorization with `AttendanceScope` for supervisors and `_own` for employees.

## 4. Migrations

All 15 pending migrations are unapplied on `general_hrms`:

| Migration | Strategy |
|---|---|
| `2026_10_30_000001…000009` (payroll) | Unchanged (committed in `a498209`). |
| `2026_10_31_000001…000005` (attendance) | Never committed or deployed, so edited in place where needed: the `legacy` event source was removed from `000002`'s source check. |
| `2026_10_31_000006_remove_legacy_attendance` | One cleanup migration (replaces the earlier uncommitted `…_remove_unused_attendance_permissions`): drops `employee_attendance_logs` and `employee_biometric_ids` and removes the three unused permissions. No table has a foreign key to the dropped tables; `employee_biometric_ids → employees` goes with its table. |

Scratch MySQL tests (never `general_hrms`):

1. `general_hrms_attendance_scratch` (all older migrations applied, legacy tables present, like the
   dev DB): rolled back the previous 15, then `migrate` (15 DONE), `migrate:rollback --step=15`
   (15 DONE; `000006` recreated the empty legacy tables), `migrate` again (15 DONE).
2. `general_hrms_cleanup_scratch` (new, empty): full `migrate` (101 DONE, no errors),
   `RolesAndPermissionsSeeder`, `AcmeDemoSeeder`. Final schema: no `employee_attendance_logs` or
   `employee_biometric_ids`; 11 attendance tables; 20 `attendance.*` permissions, none of the removed
   three; source check without `legacy`. Demo data: 5 inactive simulator devices named "… (demo)"
   with `SIM-` serials, 36 mappings, 1,841 accepted + 2 unmapped simulated events, calculated days.

Both scratch databases were left in place (dropping databases is not done by the agent); drop them
when no longer needed.

## 5. Tests

| Suite | Result |
|---|---|
| Unit/Attendance, Feature/Attendance | OK 6, OK 56 |
| Unit/Payroll, Feature/Payroll | OK 22, OK 80 |
| Feature/Leave, People, SelfService, Reports | OK 92, 33, 32, 117 |
| Full suite | 695 tests, 6 errors = baseline `MaintenanceLeavesControllerTest`, no other failures |
| `npm run build` | OK |
| Pint | Attendance-related files clean; five legacy files with pre-existing style failures were edited minimally and not reformatted |

Details: `../autonomous-work/TEST_RESULTS.md`.

## 6. Physical hardware still required

- Hikvision DS-K1T321 on the customer's firmware: pagination beyond one page, face/card minor codes,
  `/ISAPI/System/time`, HTTP listening payload, HTTPS certificate, log wrap / `serialNo` reset.
- ZKTeco PUSH devices: handshake and `TransFlag`, ATTLOG columns and status codes, `TimeZone=`, HTTPS.
- The connector on a real customer LAN (offline spool and recovery).
No simulator result is a compatibility claim.

## 7. Production readiness checklist

1. Review and commit the attendance work; run CI.
2. DPO confirmations and an approved retention schedule (`SECURITY_AND_PRIVACY.md`); removing test
   data does not replace a production retention policy.
3. Make sure nothing outside this repository writes to `employee_attendance_logs` (for example a
   deployment of `feature/hikvision-api`): the table is dropped by `000006`.
4. Back up; `php artisan migrate` (15 migrations); `php artisan event:cache`; `npm run build`;
   scheduler running (`attendance:sync-devices` every 5 minutes).
5. Register devices and connectors (HTTPS, firewall allow-lists, no devices exposed to the internet),
   map users in Enrollments, *Test connection*, complete the vendor hardware checklists.
6. Configure the attendance policy, roster and holiday jurisdictions.
7. Switch pay groups to `time_source = attendance` only after a parallel period comparing approved
   attendance summaries with current payroll inputs.
8. Grant `attendance.scope.all`, `attendance.log.view` and `attendance.enrollment.*` deliberately.
