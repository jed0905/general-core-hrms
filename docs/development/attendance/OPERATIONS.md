# Attendance Operations Guide

Installation, deployment, setup and day-to-day operation. The production readiness checklist is in
`PRE_COMMIT_AUDIT.md` §9; the local connector is described in `NETWORK_TOPOLOGIES.md`.

## 1. Deploy

1. Back up the database. `php artisan migrate` runs `2026_10_31_000001…000006` (new tables; additive
   nullable `holidays.country_code/subdivision_code`; `pay_groups.time_source` default
   `manual_inputs`; a default attendance policy; new permissions granted additively). The payroll
   migrations `2026_10_30_*` must run first (attendance summaries reference payroll periods).
   `000006` drops the legacy `employee_attendance_logs` / `employee_biometric_ids` tables (and their
   rows) and removes the never-checked `attendance.update/delete/reprocess` permissions.
2. `npm run build`.
3. **`php artisan event:cache`** if the environment caches events (this one does): the new
   `PayrollRunFinalized` listener is ignored until the cache is rebuilt.
4. The scheduler must run (`* * * * * php artisan schedule:run`, or `php artisan schedule:work` in a
   terminal for local development). **HTTP Listening (push) is the primary path** for Hikvision
   terminals (`POST /attendance/push/hikvision/{token}`; see
   `docs/integrations/HIKVISION_HTTP_LISTENING_LARAVEL_GUIDE.md`); ISAPI polling is the recovery path.
   `attendance:sync-devices` runs every minute but pulls a device only when needed:
   - devices whose push heartbeat (every 30 s) or pushed event arrived within
     `ATTENDANCE_PUSH_HEALTHY_SECONDS` (120) are skipped, except one catch-up pull every
     `ATTENDANCE_PUSH_RECONCILE_MINUTES` (60);
   - devices without working push are pulled every run from the end of the last completed pull minus
     `ATTENDANCE_SYNC_OVERLAP_MINUTES` (10) plus measured clock drift;
   - a failed pull doesn't advance the cursor; pulls are ingested page by page;
   - a daily pass at `ATTENDANCE_LOOKBACK_DAILY_AT` (02:30) re-reads 24 hours (`--lookback=1440`) for
     events a device stores late;
   - `--force` (or *Synchronize now*) pulls immediately regardless.
   Both schedule entries are `withoutOverlapping`, each device has its own lock, and an offline device
   doesn't stop the others. Output: `storage/logs/attendance-sync.log` (`-v` lists skipped devices);
   push requests: `storage/logs/attendance-push.log` (status actually sent, no URLs/tokens).
   `schedule:work` also runs the other scheduled tasks (midnight: `employee-movements:apply-due`,
   `recruitment:expire-offers`, `leaves:credit`).

## 2. Set up

1. Attendance > Settings: review the default policy (grace, pairing, overtime approval, night window,
   timezone fallback); add per-legal-entity policies if rules differ.
2. Time module: shifts, weekly work schedules, employee schedule assignments (existing pages).
   Attendance > Shift Roster for rotating/date-specific shifts and rest days.
3. Holidays (Time module): set `country_code`/`subdivision_code` for jurisdiction-specific holidays
   (blank = everywhere).
4. Attendance > Devices: register each device (see `NETWORK_TOPOLOGIES.md` for the method), set
   credentials, *Test connection*, *Synchronize now*.
5. Attendance > Enrollments: map each person's device user ID to the employee, per device. Earlier
   punches of that device user are matched automatically.
6. Payroll > Setup > Pay groups: set *Time quantities come from* = attendance for pay groups paid
   from attendance.

### First pull from a device that already holds history

A new device's first pull (scheduled or *Synchronize now*) reads the last
`ATTENDANCE_SYNC_INITIAL_DAYS` (7) days of the requested event codes. To start with a narrow test
window instead, pull it explicitly before starting the scheduler; it sets the cursor for later pulls:

```bash
php artisan attendance:sync-devices --device=<id> --from="2026-10-31 12:00" --to="2026-10-31 13:00"
```

Times without an offset are in the device's timezone. Leave the scheduler stopped until the first
controlled pulls look right.

### Near-real-time events (Hikvision alertStream listener)

Primary path for Hikvision devices polled over ISAPI; scheduled polling stays on as the recovery path.
Both use the same ingestion and dedupe key (device + `serialNo` + instant), so an event delivered by
both is stored once (a later poll counts it as a duplicate).

```bash
php artisan attendance:listen --device=<id>          # keep running in its own terminal
php artisan attendance:listen --device=<id> --status # state, counts, last error (from another terminal)
tail -f storage/logs/attendance-stream.log           # connection state and stored events
```

- Stop with Ctrl-C or `kill -TERM <pid>` (graceful; state becomes `stopped`). One listener per device.
- It reconnects by itself (backoff 1 s doubling to `--max-backoff`, default 60 s). This firmware sends no
  heartbeat when idle, so after `--idle` seconds (default 90) without data it reconnects; that's
  normal. Right after a drop the device may answer 404 once; the next attempt succeeds.
- On connect the device replays stored records it hasn't delivered on a stream yet (`currentEvent:
  false`). Replays older than the polling cursor minus the overlap are skipped, so the listener never
  imports old history.
- No queue worker is needed. Code changes need a listener restart (a running PHP process keeps the
  old code).
- Restart after a reboot on a Mac: a LaunchAgent (`~/Library/LaunchAgents/<label>.plist`) with
  `ProgramArguments` = `/opt/homebrew/bin/php`, `artisan`, `attendance:listen`, `--device=<id>`,
  `WorkingDirectory` = the project, `KeepAlive` = true, `RunAtLoad` = true, then
  `launchctl load ~/Library/LaunchAgents/<label>.plist`. Do the same for `schedule:work`, or use cron
  (`* * * * * cd <project> && php artisan schedule:run`). On a server use Supervisor or systemd.
  A sleeping Mac pauses both; they recover when it wakes.

## 3. Daily / per period

- Watch the dashboard (device status, missing punches, unmapped users).
- Fix missing or wrong punches with **adjustments** (approved by a second person). Raw logs never
  change.
- Approve overtime with adjustments when the policy requires approval.
- Before payroll: Attendance > For Payroll → choose the payroll period → *Generate* → review
  (missing punches block approval; "minutes without a payroll category" means the localization has
  no category for that bucket) → *Approve*. Payroll refuses to calculate employees of attendance
  pay groups without an approved summary.
- Corrections after payroll finalized: the summary is flagged *changed after payroll*; pay or recover
  the difference with a payroll adjustment. Finalized payroll never changes.

## 4. Without hardware

Register a device with vendor *Simulator* and edit its scenario JSON on the device page, e.g.

```json
{"generate": {"from": "2026-10-12", "to": "2026-10-16", "users": ["1001", "1002"], "in": "08:00", "out": "17:00",
  "jitter_minutes": 10, "seed": 7, "duplicate_every": 5, "missing_every": 9, "reverse": true},
 "events": [{"user": "1001", "time": "2026-10-13 12:00:00", "type": "break_out", "id": "x1", "available_at": "2026-10-20 00:00:00"}],
 "online": true, "fail_times": 0, "failure": null, "clock_drift_seconds": 0}
```

Simulated events are labelled SIMULATED everywhere. Use *Raw Logs > Import* for CSV
(`employee_number,date_time,type,device_serial`).

## 5. Troubleshooting

| Symptom | Check |
|---|---|
| Device *offline* | Network path HRMS → device (topology 1), connector running (2), or device push target (3); retries happen automatically (3 attempts). |
| `auth_failed` | Device account/password; Hikvision locks accounts after failed attempts. |
| `device_error … badParameters / Invalid Content` | Page size too large for the model (try 10), unsupported minor code, or old firmware. |
| Events stay *Unmapped user* | Enroll the device user ID for that device. |
| Events *Invalid time* | Device clock unset or wrong; fix the clock (NTP); the raw value is kept. |
| Wrong day for night shifts | Check the shift is marked overnight and the policy window (default 4 h before shift start). |
| Times off by hours | Device timezone on the device record vs the device's own clock setting; legal entity timezone for employees. |
| Payroll error TIME_INPUTS_NOT_READY | Generate and approve the attendance summary for that period. |

## 6. Tests

```bash
php -d memory_limit=1G vendor/bin/phpunit tests/Unit/Attendance
php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Attendance
php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Attendance/AttendancePayrollIntegrationTest.php
```
All device tests use the simulator or HTTP fakes. Hardware checks are manual (vendor documents §
"Hardware verification still required").
