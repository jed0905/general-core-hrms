# Attendance Implementation Plan

| Phase | Scope | Main artifacts |
|---|---|---|
| 0 | Commit payroll (`a498209`), audit, architecture | `ARCHITECTURE.md`, this plan |
| 1 | Foundation | migrations `2026_10_31_*`, models, policies, permissions, `attendance_policies`, roster |
| 2 | Device management | `Devices/Contracts`, `DeviceAdapterRegistry`, `DeviceService`, encrypted credentials, sync runs, device pages |
| 3 | Simulator + ingestion | `SimulatorAdapter`, `EventIngestionService`, `DeviceSyncService`, `SyncAttendanceDevicesCommand`, manual entry, CSV import |
| 4 | Hikvision | `HikvisionIsapiAdapter` (pull, paging, device info, time check), push receiver, `HIKVISION_DS_K1T321.md` |
| 5 | ZKTeco | `ZktecoAdmsAdapter` + `/iclock/*` receiver (ATTLOG), `ZKTECO_INTEGRATION.md` (TCP/SDK not implemented: unverified) |
| 6 | Engine | `AttendanceCalculator`, `ShiftResolver`, `PunchPairer`, adjustments, leave & holiday integration |
| 7 | Frontend & reports | pages under `resources/js/pages/app/Attendance/*`, reports in `Reports/Attendance` |
| 8 | Payroll integration | `AttendancePeriodService`, `AttendanceTimeInputProvider`, `MapsAttendanceToPremiums`, `pay_groups.time_source` |
| 9 | Hardening & docs | security review, connector, docs, hardware checklist, full test run |
| 10 | Legacy removal (2026-10-31) | Legacy pages, reports, DTR suite, tardiness job, models, tables and unused permissions removed; see `LEGACY_REMOVAL.md` and `PRE_COMMIT_AUDIT.md`. |

Tests: `tests/Feature/Attendance/*`, `tests/Unit/Attendance/*`; device tests use the simulator and
HTTP fakes (`Http::fake`) with payloads shaped like the tested DS-K1T321 integration. There is no
automated hardware test; hardware checks are the manual checklists in the vendor documents.

## Status (2026-10-30)

All phases implemented; see `../autonomous-work/PROGRESS.md`. Hardware verification for Hikvision
and ZKTeco is pending (no device available in this session).
