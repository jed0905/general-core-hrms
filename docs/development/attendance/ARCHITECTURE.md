# Attendance Module Architecture

Companion docs: `IMPLEMENTATION_PLAN.md`, `DEVICE_COMPATIBILITY.md`, `HIKVISION_DS_K1T321.md`,
`ZKTECO_INTEGRATION.md`, `NETWORK_TOPOLOGIES.md`, `SECURITY_AND_PRIVACY.md`, `OPERATIONS.md`,
`LEGACY_REMOVAL.md`, `PRE_COMMIT_AUDIT.md`.

## 1. Audit findings (verified against code, 2026-10-30)

| Finding | Consequence |
|---|---|
| **Prior Hikvision work** lives only on the unmerged branch `feature/hikvision-api` (commit `3203d23`, JED, 2026-09-02): `HikvisionAttendanceService` (ISAPI pull), `HikvisionWebhookController` (push, **not routed**), `config/hikvision.php` (credentials in `.env`), package `shaykhnazar/hikvision-isapi`. | Reused as the technical reference (endpoints, payload fields, status mapping). Not merged: it has an unauthenticated `GET /api/hikvision/sync-test`, a hard-coded employee `220464`, a `dd()` in `DashboardController`, no pagination beyond 30 results, full payloads in logs. The adapter is reimplemented with Laravel's HTTP client (`withDigestAuth`), **no new package**. |
| Verified ISAPI usage from that code: `POST /ISAPI/AccessControl/AcsEvent?format=json` with `AcsEventCond{searchID, searchResultPosition, maxResults:30, major:5, minor:38, startTime, endTime (ISO 8601 with offset)}` → `AcsEvent.InfoList[]{serialNo, time, employeeNoString, name, cardNo, attendanceStatus, deviceName}`; `GET /ISAPI/System/deviceInfo` → `DeviceInfo.model`; push body field `AccessControllerEvent` (or `event_log`) JSON `{eventType, deviceID, shortSerialNumber, dateTime, AccessControllerEvent{serialNo, majorEventType, subEventType, currentVerifyMode, attendanceStatus, employeeNoString, name, cardNo}}`, `heartBeat`. | Hikvision adapter (pull + push). Firmware of the tested unit was **not recorded**. |
| `employee_attendance_logs` (raw punches; `employee_id` is a device user ID string) and `employee_biometric_ids` (one **globally unique** `biometric_id` per row) are read by Time Logs, My Attendance, the employee dashboard and the raw attendance reports. What fills the table today is not in the repo. | **Removed (2026-10-31).** Every consumer was moved to the attendance module and the two tables are dropped by `2026_10_31_000006_remove_legacy_attendance` (their rows were test/demo data). See `LEGACY_REMOVAL.md`. |
| No DTR/attendance computation exists. Time module provides shifts (start/end, break, required hours, overnight, flexible), weekly work schedules and effective-dated employee schedule assignments; `EmployeeWorkScheduleService::resolveDay()` is the single "scheduled on this date" rule. | Engine reuses the Time module schedules and adds date-specific **roster overrides** (rotating/multiple shifts). |
| `holidays` has no country (types `regular`, `special_non_working`, `special_working`, `company`). | Additive nullable `country_code`, `subdivision_code` columns (null = applies everywhere, the current behavior). |
| Payroll consumes time through `TimeInputProvider` (approved `payroll_time_inputs`). | New `AttendanceTimeInputProvider`; pay groups choose `time_source` (`manual_inputs` default = unchanged behavior, or `attendance`). |
| Existing permissions `attendance.view/create/update/delete/adjust/approve_adjustment/process/reprocess/finalize/export/view_own/export_own`, `shift.*`, `work_schedule.*`, `holiday.*`. | Reused; device/enrollment/log/config permissions added. `update`, `delete` and `reprocess` were never checked and are removed (`2026_10_31_000006`). |

## 2. Layers

```
Device adapters (vendor-specific)            Attendance core (vendor-independent)
  Hikvision (ISAPI pull/push)                  DeviceAdapterRegistry  ──►  DeviceSyncService
  ZKTeco (ADMS/PUSH receiver)                  EventIngestionService (normalize, map, dedupe)
  Simulator                                    attendance_events (raw, append-only)
  Manual / CSV / connector                      AttendanceCalculator (shift, pairing, rules) ──► attendance_days
                                               AttendanceAdjustmentService (approval, never edits raw)
                                               AttendancePeriodService ──► attendance_period_summaries
                                               AttendanceTimeInputProvider ──► Payroll (TimeInputProvider)
```

- Adapters implement `App\Services\Attendance\Devices\Contracts\DeviceAdapter` and declare **capabilities**
  (`test_connection`, `device_info`, `fetch_events`, `incremental`, `push`, `time_check`, ...).
  An unsupported operation throws `UnsupportedDeviceOperation`; nothing is faked.
- Every source (real pull, push, connector, simulator, manual, CSV) produces
  `NormalizedEvent` objects that go through the **same** `EventIngestionService`.

## 3. Data model (new tables, migrations `2026_10_31_*`)

- `attendance_connectors`: on-premises connector identity (HMAC secret encrypted).
- `attendance_devices`: vendor, model, serial (unique per vendor), name, legal entity, location,
  timezone, connection method, host/port/TLS, encrypted credentials, push token (hashed), page size,
  sync cursor, capabilities, firmware, status/last seen/last sync/last error, simulation settings, active.
- `attendance_device_sync_runs`: per sync/push/import batch: trigger, status, counters, error.
- `biometric_enrollments`: device × employee × device user ID (encrypted card number, external ref),
  status, enrolled/deactivated by/at. One **active** mapping per (device, device user ID) enforced by a
  unique generated column; history kept (reassignment = deactivate + new row).
- `attendance_events`: raw, append-only. Source, device, vendor/model, device user ID, device event ID,
  raw time string + device timezone + normalized UTC instant + local date, event type, verify mode,
  sanitized encrypted payload, received at, sync run, `dedupe_key` (unique), status
  (`accepted|unmatched|invalid`; duplicates are never stored) + reason, created_by for manual entries.
- `attendance_policies`: grace periods, pairing mode, debounce, break deduction, overtime threshold and
  approval, night window, day boundary; per legal entity or default.
- `employee_shift_assignments` (roster): date-specific shift or rest day overriding the weekly schedule.
- `attendance_days`: computed per employee per work date with schedule snapshot, minutes by category,
  status, exceptions, used event IDs, `locked_at`.
- `attendance_adjustments`: add/void punch, approve overtime, override day; original/corrected values,
  reason, requested/reviewed/approved by/at. Raw events are never edited: voids are recorded here.
- `attendance_period_summaries`: payroll-period quantities per employee (`draft|approved`), consumed by
  payroll; `consumed_run_id` once used by a finalized run.
- `attendance_audit_logs`: append-only audit of device, credential, enrollment, adjustment, summary changes.
- `holidays` + `country_code`, `subdivision_code` (nullable); `pay_groups` + `time_source`.
- Removed: `employee_attendance_logs`, `employee_biometric_ids` (legacy; dropped by `2026_10_31_000006`).

Foreign keys: devices → legal entities, locations, connectors (null on delete); enrollments →
devices, employees (restrict); events → devices, enrollments, employees, sync runs (restrict / null);
days → employees, shifts, holidays, policies; adjustments → employees, events, users; summaries →
employees, payroll periods, payroll runs (`consumed_run_id`). Unique keys: device (vendor, serial);
event `dedupe_key`; active enrollment (device, device user ID); day (employee, work date); roster
(employee, work date); summary (employee, payroll period).

## 4. Attendance calculation (per employee per work date)

1. **Schedule**: roster override → else Time-module weekly schedule (`resolveDay`) → shift times in the
   employee's timezone (legal entity of the primary payroll assignment, else device, else policy/app).
   Overnight shifts end the next day; the work date is the shift's start date.
2. **Window**: events in `[shift start − policy.window_before, +24h)`; without a shift, the local
   calendar day from `policy.day_start_time`. Every event belongs to exactly one work date.
3. **Events**: accepted, mapped, not voided by an approved adjustment, plus approved manual punches;
   debounced (`policy.debounce_minutes`).
4. **Pairing** (`policy.pairing_mode`): `in_out` pairs typed punches in order (unknown types alternate);
   `first_last` uses first and last only. Unpaired punches → `missing_punch` exception, status `incomplete`.
5. **Minutes**: worked (Σ pairs − unpaid break when no break punches and `deduct_break`), late (after
   start + grace), undertime (before end − grace), overtime (beyond scheduled end / required minutes,
   ≥ threshold; approved when policy requires approval), night (overlap with the policy night window).
   All instant arithmetic is in UTC, so DST transitions are handled.
6. **Day type**: workday, rest day, or holiday (jurisdiction-matched holiday, non-working types).
   Minutes are bucketed by (day type, holiday type, overtime?, night?).
7. **Leave**: approved leave only. Full day → `on_leave` (no absence); partial → expected minutes
   reduced. Leave pay effects stay in payroll's leave provider, so attendance never double counts them.
8. **Status**: present, late, incomplete, absent, rest_day, holiday, on_leave, no_schedule.

## 5. Payroll integration

`AttendancePeriodService` aggregates `attendance_days` for a payroll period into core quantities
(`days_worked`, `hours_worked`, `absence_days`, `late_hours`, `undertime_hours`) and premium categories.
The mapping of (day type, holiday type, overtime, night) to a premium category is supplied by the
payroll localization (`MapsAttendanceToPremiums`), so no country rule enters the attendance engine.
Summaries are approved (`attendance.finalize`); only approved summaries feed payroll. Payroll snapshots
the quantities it used. Corrections after a summary was consumed by a finalized run mark it
`changed_after_payroll`; differences are paid via payroll adjustments, never by recalculating history.

## 6. Connectivity (see `NETWORK_TOPOLOGIES.md`)

Direct pull (same network/VPN), device push (Hikvision HTTP host notification, ZKTeco ADMS), and an
optional HRMS connector (`php artisan attendance:connector`) that polls LAN devices, spools locally and
posts HMAC-signed batches (timestamp + nonce, replay window) to the HRMS.

## 7. Single source of truth

The attendance module is the only attendance system. Attendance pages, My Attendance, the employee
dashboard card and the Attendance reports read `attendance_events`, `attendance_days`,
`attendance_adjustments` and `attendance_period_summaries` through `EmployeeAttendanceService`,
`AttendanceEventEntryService`, `AttendanceScope` and the report classes. There is one ingestion path
(`EventIngestionService`) and one engine (`AttendanceRecalculator` → `AttendanceCalculator`).
