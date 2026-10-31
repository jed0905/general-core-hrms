# Legacy Attendance Removal (record)

`general_hrms` is a new system and its legacy attendance rows were test/demo data, so the legacy
attendance system was removed completely on 2026-10-31. Nothing was migrated. This file records what
was removed; it is not a procedure.

## Removed

| Area | Removed |
|---|---|
| Tables | `employee_attendance_logs`, `employee_biometric_ids`, dropped by `2026_10_31_000006_remove_legacy_attendance`. Their create migrations (`2025_05_19_000018`, `2026_01_07_082554`) stay in the history because they ran on existing databases. |
| Models | `EmployeeAttendanceLog`, `EmployeeBiometricId` |
| Time Logs | `Web\Time\TimeLogController`, `TimeLogFilterRequest`, `App\Services\AttendanceLogService`, page `Time/TimeLogs`, route `time.time-logs.index`, menu item |
| My Attendance (old) | `Web\Time\MyAttendanceController`, `AttendanceRangeRequest`, `App\Services\EmployeeAttendanceService`, page `Time/MyAttendance`, routes `time.my-attendance.*` |
| Reports | `AttendanceSummaryReport` (`attendance-summary`), `RawAttendance` trait; `AttendanceLogReport` rewritten on `attendance_events` |
| Old DTR | `HrManagement\{DailyTimeRecord,AttendanceLogEventTypes,EmployeeWorkShifts}Controller`, `DailyTimeRecordService`, `PrintDtrService`, `AttendanceLogEventTypesService`, `EmployeeWorkShiftService`, `Store/UpdateAttendanceLogEvent*Request`, pages `HrManagement/DailyTimeRecord/**`, `DailyTimeRecordTabs.vue`, `views/templates/dailytimerecord/*`, observers `EmployeeAttendanceLog{Documents,Event}Observer` |
| Jobs / schedule | `CheckTardinessJob` (daily 09:30), `TardinessMail`, `emails/tardiness` view |
| Imports | Web "import legacy logs" action and `ImportLegacyEventsRequest`; the `legacy` event source |
| Permissions | `attendance.update`, `attendance.delete`, `attendance.reprocess` (never checked) |
| Legacy biometric-ID field | Biometric fields in the unrouted legacy employee edit (`UpdateEmployeeJobDetailsFormRequest`, `EmployeeJobDetailsResource`, `EmployeeTableResource`, `EmployeeResource`, `components/EmployeeProfile/JobForm.vue`); the rest of those files is unchanged |
| Other | Dangling imports in `routes/web.php` and `app/Services/LeaveService.php`; obsolete tests (`tests/Feature/Time/TimeLogsTest.php`, legacy fixtures in report/self-service/dashboard tests) |

The unused legacy `LeaveApplicationFormRequest` kept its wellness-leave rule; its "has the employee
reported for work" check now reads accepted `attendance_events` instead of the legacy table.

## Replacements

Attendance → Raw Logs (`attendance.logs.index`), My Attendance (`attendance.my.index`/`.export`),
the employee dashboard card (`EmployeeAttendanceService`), reports `attendance-log`,
`attendance-daily`, `attendance-totals`, `attendance-device-sync`, device-specific mappings in
`biometric_enrollments`.

`tests/Feature/Attendance/LegacyAttendanceRemovalTest.php` asserts that the legacy routes, tables and
permissions are gone and that no application code mentions the legacy models or tables.
