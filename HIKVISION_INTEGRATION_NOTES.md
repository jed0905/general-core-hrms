# Hikvision DS-K1T321MFX ↔ General HRMS: Integration Notes

How the HRMS (Laravel 12, PHP 8.3) talks to a physical Hikvision access-control/attendance terminal,
which connection methods were tried, and what works. Written 2026-10-31 after testing on real
hardware. No credentials or tokens are included.

## 1. Environment

| Item | Value |
|---|---|
| Device | Hikvision DS-K1T321MFX, firmware V4.61.0 build 260828 |
| Device address | `192.168.88.215`, HTTP 80 / HTTPS 443, HTTP Digest authentication |
| Device clock | NTP, UTC+08:00 (the device reports the zone as `Asia/Shanghai`) |
| HRMS host | MacBook on the same LAN, `192.168.88.217` |
| HRMS app | `php artisan serve --host=127.0.0.1 --port=8010` (local UI) |
| Database | MySQL/MariaDB `general_hrms` (attendance migrations applied 2026-10-31) |
| HRMS device record | device id `1`, vendor `hikvision`, connection method `isapi_pull`, timezone `Asia/Manila`, page size 30 |

Device credentials are entered in the HRMS UI (Attendance → Devices) and stored encrypted (Laravel
`encrypted` cast). They are never printed or logged.

## 2. HRMS attendance architecture (relevant parts)

```
Hikvision terminal
   │  (1) ISAPI polling: HRMS → device, every minute          [WORKS]
   │  (2) ISAPI alertStream: HRMS keeps a connection open     [WORKS]
   │  (3) HTTP host push: device → HRMS                       [NOT WORKING YET]
   ▼
HikvisionIsapiAdapter (classification: major/minor filter, field mapping)
   ▼
EventIngestionService (dedupe key, employee mapping, unmatched handling)
   ▼
attendance_events (raw, append-only) → attendance engine → attendance_days → payroll summaries
```

Key code:

| Purpose | File |
|---|---|
| Hikvision ISAPI adapter (polling, notification parsing, classification) | `app/Services/Attendance/Devices/Adapters/HikvisionIsapiAdapter.php` |
| Polling orchestration, cursor, retries, locks | `app/Services/Attendance/DeviceSyncService.php` |
| Shared ingestion and dedupe | `app/Services/Attendance/Ingestion/EventIngestionService.php` |
| Polling command | `app/Console/Commands/SyncAttendanceDevices.php` (`attendance:sync-devices`) |
| Schedule | `app/Console/Kernel.php` |
| alertStream client (ext-curl, digest) | `app/Services/Attendance/Devices/Streaming/HikvisionAlertStream.php` |
| Multipart stream parser | `app/Services/Attendance/Devices/Streaming/MultipartStreamParser.php` |
| Stream/push handling (classification + history guard + ingestion) | `app/Services/Attendance/DeviceStreamService.php` |
| alertStream listener command | `app/Console/Commands/ListenAttendanceDevices.php` (`attendance:listen`) |
| HTTP push receiver | `app/Http/Controllers/Device/HikvisionPushController.php`, route in `routes/Attendance/device.php` |
| Config | `config/attendance.php` |
| Docs | `docs/development/attendance/HIKVISION_DS_K1T321.md` (§6-8), `OPERATIONS.md` |

### Shared rules for every connection method

- **Classification.** Only `majorEventType` 5 with the configured minor codes (default `[38]`) and a
  non-empty `employeeNoString` become attendance punches. Door, alarm and operation events are
  ignored. Minor codes are configurable per device in the UI ("Event minor codes").
- **Status mapping.** `attendanceStatus` `checkIn` / `checkOut` / `breakIn` / `breakOut` /
  `overtimeIn` / `overtimeOut` → `check_in`, `check_out`, …; missing status → `unknown` (pairing
  then alternates in/out).
- **Timestamps.** The raw device string (e.g. `2026-10-31T12:33:45+08:00`) is stored as-is plus the
  UTC instant; the offset in the string is used, so the `Asia/Shanghai` zone name has no effect.
- **Dedupe key.** device + `serialNo` + instant. The same event arriving by polling, stream and push
  is stored once; later deliveries count as duplicates. Concurrent inserts are also handled.
- **Unknown employee numbers.** Stored as `unmatched` and matched automatically when the device user
  is mapped to an employee (Attendance → Enrollments).
- **Not stored.** Names, card numbers, face rectangles and pictures are dropped. Only identifiers and
  codes are kept in an encrypted payload.

## 3. Method 1: ISAPI polling (AcsEvent search). **Works.**

**Requests (HRMS → device, Digest auth via Laravel HTTP client):**

| Endpoint | Use | Result on this firmware |
|---|---|---|
| `GET /ISAPI/System/deviceInfo?format=json` | connection test, model/serial/firmware | Works, but **the answer has no JSON `DeviceInfo` section despite `?format=json`** (most likely XML). The adapter was changed to accept XML. Reports model `DS-K1T321`. |
| `GET /ISAPI/System/time?format=json` | clock drift | Works (drift 0 s). XML also accepted. |
| `POST /ISAPI/AccessControl/AcsEvent?format=json` | event search | Works, JSON. Body `AcsEventCond{searchID, searchResultPosition, maxResults, major, minor, startTime, endTime}` (times in device-local ISO 8601 with offset). |

**Pagination.** `responseStatusStrg` is `MORE` until the last page (`OK`). Verified with 616 records
over 21 pages of 30, and through the adapter with page size 5 (6 pages, 29 minor-38 records).

**Scheduling and cursor (after fixes):**

- `attendance:sync-devices` runs **every minute** (`withoutOverlapping(10)`), output appended to
  `storage/logs/attendance-sync.log`.
- The cursor stores the end of the last **completed** pull (`sync_cursor.pulled_to`). Each run reads
  from `pulled_to − 10 min` (configurable `ATTENDANCE_SYNC_OVERLAP_MINUTES`) minus measured clock
  drift, up to now. A failed pull doesn't advance the cursor, so outages are recovered.
- A brand-new device's first pull reads 7 days back (`ATTENDANCE_SYNC_INITIAL_DAYS`).
- **Hourly reconciliation**: `attendance:sync-devices --lookback=1440` at :30 re-reads 24 h for
  events a device stores late.
- Per-device cache lock: a device is never pulled twice at once; an offline device doesn't stop others.
- Manual narrow window: `php artisan attendance:sync-devices --device=1 --from="2026-10-31 12:30" --to="2026-10-31 12:40"`.

**Bug found and fixed:** the original cursor was "last *accepted* event − 24 h"; it never advanced
for unmapped-only or quiet periods, so windows grew without bound (first "Synchronize now" clicks
read 7 days).

**Measured:** scan at 13:27:37 stored by the scheduled poll at 13:28:00 (23 s; worst case ~60 s).

## 4. Method 2: ISAPI alertStream (persistent event stream). **Works (primary path).**

**Capability check (read-only):**

| Endpoint | Result |
|---|---|
| `GET /ISAPI/System/capabilities` | `isSupportSubscribeEvent=true`, `isSupportHttps=true` |
| `GET /ISAPI/Event/notification/subscribeEventCap` | heartbeat 1-30 s; `AccessControllerEvent` minor events include `0x26` (38) and `0x4b` (75) |
| `GET /ISAPI/Event/capabilities` | 404 `notSupport` |
| `POST /ISAPI/Event/notification/subscribeEvent` | not tried (not needed) |

**Connection:** `GET /ISAPI/Event/notification/alertStream` with Digest auth → 401 challenge →
`200`, `Content-Type: multipart/mixed; boundary=MIME_boundary`, connection stays open. Each part:

```
--MIME_boundary
Content-Disposition: form-data; name="AccessControllerEvent"
Content-Type: application/json; charset="UTF-8"
Content-Length: 562

{ "ipAddress": "...", "dateTime": "2026-10-31T15:44:16+08:00", "eventType": "AccessControllerEvent",
  "AccessControllerEvent": { "majorEventType": 5, "subEventType": 38, "serialNo": 646,
    "employeeNoString": "...", "attendanceStatus": "breakIn", "label": "Break In",
    "currentVerifyMode": "faceOrFpOrCardOrPw", "currentEvent": true, ... } }
```

**Important device behaviors observed:**

1. **Laravel's HTTP client (Guzzle) cannot do Digest auth on a streamed request** (the stream handler
   has no digest support → 401). The listener uses PHP `ext-curl` directly with `CURLAUTH_DIGEST`.
2. **History replay:** on connect the device replays every stored record it has not yet delivered on
   a stream (`currentEvent: false`), ~4 records/s, continuing across connections from where the last
   one stopped. The listener **skips replayed records older than the polling cursor − overlap**, so
   history is never imported (first drain: 200 records, 178 ignored, 22 skipped, 0 stored).
3. **No heartbeat** parts when idle. The listener reconnects after 90 s without data (harmless).
4. Right after a drop, the device sometimes answers 404 once; the next attempt works.

**Listener:** `php artisan attendance:listen --device=1` (own terminal). Reconnects with backoff
1 s → 60 s, stops cleanly on Ctrl-C/SIGTERM, logs to `storage/logs/attendance-stream.log` (no
payloads), status via `php artisan attendance:listen --device=1 --status`. Events go through
`DeviceStreamService::handle()` → `DeviceSyncService::receivePush()` (source `device_push`, trigger
`stream`).

**Verified with real scans:**

| Scan (device time) | Status | Stored | Delay | Later poll |
|---|---|---|---|---|
| 15:44:16, serial 646 | Break In | 15:44:17 via stream | ~1 s | counted as duplicate; 1 row |
| 15:48:08, serial 652 | Check Out | 15:48:09 via stream | ~1 s | counted as duplicate; 1 row |

Ran ~2 h with automatic reconnects (stream interruption, idle timeouts, transient 404, Mac sleep).

**Bug found and fixed:** the long-running listener held an old device instance and wrote its stale
cursor back after storing an event (polling cursor moved backwards → wider window, no loss).
`touchDevice()` now merges into the freshly read cursor.

**Not yet tested:** the device losing network mid-stream with a scan during the outage.

## 5. Method 3: HTTP host notification (device → HRMS push). **Not working yet.**

**Configuration API (read first, original saved):**
`GET/PUT /ISAPI/Event/notification/httpHosts/<id>` (XML). 2 slots, both unconfigured initially
(slot 1 HTTP/XML to `0.0.0.0:80`, slot 2 EHome). Options: protocol HTTP/HTTPS/EHome, format XML/JSON,
IP or hostname, port, URL ≤ 128 chars, device-side auth none/basic/MD5 digest (password 8-16 chars),
heartbeat 1-30 s, `eventMode` all/list. A PUT must include the event element exactly as returned by
GET (empty `minorAlarm`, `minorException`, `minorOperation`), otherwise 400 `MessageParametersLack`.
`/httpHosts/1/test` is not supported. Originals saved in `storage/app/device-config-backups/`.

**Receiver:** `POST /attendance/push/hikvision/{token}` (stateless route, no session/CSRF;
per-device token stored only as a SHA-256 hash; only requests from the device's own IP accepted;
reads JSON, XML or multipart; pictures ignored; history guard; one log line per request in
`storage/logs/attendance-push.log` without values). For the test a second server was bound to the
LAN: `php artisan serve --host=192.168.88.217 --port=8011` (macOS firewall was off).

**Test (with approval):** slot 1 → `http://192.168.88.217:8011/attendance/push/hikvision/<token>`,
HTTP, JSON, no device-side auth, `eventMode=list`, minor `0x26`.

**Observed:**

- The device posted within seconds from `192.168.88.215`: `multipart/form-data` with one part
  `AccessControllerEvent` (JSON). The receiver parsed it.
- It sent its **stored log first** (serial 25 from 2025-11-07), not live events.
- It **re-sent the same record about once per second** although the receiver returned
  `200 OK` (`text/html`, body `OK`): the firmware did not accept that as an acknowledgement. The
  expected acknowledgement format is unknown (Hikvision partner documentation).
- One historical record was stored before the history guard existed (event id 9, unmapped, type
  unknown); raw events are append-only, so it remains.
- `php artisan serve` prints request paths, so the URL token appeared in its console output.

**Rollback done:** receiver stopped, slot 1 restored to the saved original (verified identical),
token revoked, console output redacted. Slot 2 was never changed. Push now skips any pushed event
behind the polling cursor.

**Open questions / next steps for push:**

1. Try answering with an ISAPI-style body (e.g. `<ResponseStatus>…<statusCode>1</statusCode>…`)
   to see whether the device advances past the first record. This is a guess, not documented.
2. Prefer device-side Basic/Digest auth (credentials in a header) over a token in the URL, which
   web servers log.
3. If push works, it would add little over alertStream (both near-real-time); polling stays as recovery.

## 6. Event codes observed on this unit (from its own 616-record log)

| major/minor | Records | With employee no. | `attendanceStatus` seen | Notes |
|---|---|---|---|---|
| 5/38 | 29 | 29 | checkIn, checkOut, breakIn, (later breakOut), none | the attendance punches; collected |
| 5/75 | 14 | 14 | none | always carries `FaceRect` (face); not collected by default |
| 5/1, 5/104 | 1 each | 1 each | none | meaning unconfirmed |
| majors 1, 2, 3, other 5/x | 572 | 0 | - | door/alarm/operation; never stored |

`currentVerifyMode` always reports the configured mode (`faceOrFpOrCardOrPw`), not the method used.
Which minor code fingerprint or card scans produce is not yet confirmed.

## 7. How to run locally

```bash
php artisan serve --host=127.0.0.1 --port=8010     # tab 1: web app (http://127.0.0.1:8010)
php artisan schedule:work                           # tab 2: polling every minute + hourly reconciliation
php artisan attendance:listen --device=1            # tab 3: alertStream listener (near real time)

tail -f storage/logs/attendance-sync.log            # polling results
tail -f storage/logs/attendance-stream.log          # stream connection state and stored events
php artisan attendance:listen --device=1 --status   # listener state
```

No queue worker is needed (queue driver `sync`). `schedule:work` also runs the app's midnight tasks
(`employee-movements:apply-due`, `recruitment:expire-offers`, legacy `leaves:credit`). A sleeping
Mac pauses everything; it catches up on wake. For production use cron
(`* * * * * php artisan schedule:run`) and a process supervisor for the listener.

## 8. Summary

| Method | Direction | Status | Latency | Notes |
|---|---|---|---|---|
| ISAPI AcsEvent polling | HRMS → device | **Works** (real scans) | ≤ ~60 s (23 s measured) | recovery path; incremental cursor; hourly 24 h reconciliation |
| ISAPI alertStream | HRMS holds connection | **Works** (real scans) | ~1 s | primary path; needs ext-curl for digest; replay guard |
| HTTP host notification | device → HRMS | **Works** (standalone receiver, real scans; §11) | ~1 s | 2xx = ack, non-2xx retried ~1/s; drains stored log on configuration; Laravel receiver needs a hardware retest |
| PushSDK | device → server (HTTP + WebSocket ports) | **Not implemented** | - | already enabled to `210.4.122.139`, offline; protocol docs partner-gated (MQTT+JSON per Hikvision) |
| ISUP 5.0 | device → server | **Not implemented** | - | already enabled to `210.4.122.139:8000`, not registered; needs Hikvision ISUP SDK in a central gateway |

All code changes are uncommitted on branch `feature/claude-agent`. Attendance tests:
78 passing (`php -d memory_limit=1G vendor/bin/phpunit tests/Feature/Attendance tests/Unit/Attendance`).


## 9. PushSDK and ISUP 5.0 investigation (2026-10-31, read-only; no device changes)

### What the device reports (verified, GET only)

The device's own web UI (JavaScript served at `https://<device>/doc/`, fetched with gzip) uses these
configuration endpoints: `ISAPI/System/Network/PUSHCfg` (PushSDK), `ISAPI/System/Network/Ehome`
(ISUP), `ISAPI/System/Network/OTAPServerList` (OTAP), next to `Event/notification/httpHosts`.

| Endpoint | Result |
|---|---|
| `GET /ISAPI/System/Network/PUSHCfg/capabilities?format=json` | fields: `enabled`, `addressingFormatType` (ipaddress/hostname), `ipAddress`, `hostName`, `protocolType` (http/https), `httpPortNo`, `httpsPortNo`, `webSocketPortNo`, `webSocketSPortNo`, `username` (1-32), `password` (1-32), `status` (online/offline) |
| `GET /ISAPI/System/Network/PUSHCfg?format=json` | **already enabled**: `210.4.122.139`, http, HTTP port 8000, HTTPS 443, WebSocket 8001, WebSocket-S 443, username `push_test` (password not returned), **status offline** |
| `GET /ISAPI/System/Network/Ehome/capabilities` | one center; IP/hostname, port, `deviceID` (1-32), `key` (8-16), `protocolVersion` **v5.0 only**, `registerStatus` |
| `GET /ISAPI/System/Network/Ehome` | **already enabled**: `210.4.122.139:8000`, device ID `USDOA`, v5.0, **registerStatus false** |
| `GET /ISAPI/System/Network/OTAPServerList(/capabilities)` | 404 `notSupport` (no OTAP on this firmware) |

Both platform-access features were configured before this work (by an unknown party) towards
`210.4.122.139`, and neither is connected. The device keeps trying to reach that address. GET does
not return the PushSDK password or the ISUP key, so **the current values cannot be restored after
an overwrite** unless whoever configured them still has them.

### What official sources say (verified)

- Hikvision Open Capabilities (TPP): Push SDK for access control uses **MQTT+JSON for authentication
  tokens or events, WebSocket for media, HTTP for configuration**; the third-party server needs a
  static IP and port; the integration documentation download is **restricted (partner login)**.
  The DS-K1T321MFX is named among devices used with Push SDK by third-party products (e.g. TimAccess).
- Hikvision user manuals (sibling models, e.g. DS-K1T670) document only the menu: Network > Device
  Access > PushSDK with Protocol Version, Server IP, Port, Device ID, Encryption Key and an "Online"
  registration status. The field list differs from this firmware's (which has HTTP/WebSocket ports
  and username/password), so field-level details are firmware-specific.
- Hikvision ISUP pages: "SDK is based on Intelligent Security Uplink Protocol (ISUP)"; only V5.0;
  the platform provides the server port and device ID; an encryption password is required; enabling
  ISUP disables other platform access (per manual; on this device both PushSDK and ISUP show enabled).
- No official public ISUP SDK download was found; Hikvision's public Linux SDK is the Device Network
  SDK (different, private protocol). Community copies of an ISUP 5.0 SDK and a Python ISUP server
  (tested only against a simulator) exist on GitHub with unclear provenance.

### Not verified (assumptions / undocumented)

- PushSDK wire format: whether events travel as MQTT over the WebSocket port, plain WebSocket JSON,
  or HTTP; the registration handshake; acknowledgement, retry and replay semantics; whether the
  device initiates the connection (the online/offline status and server-address fields suggest it
  does, which would work across NAT/ISPs without VPN, but this is not documented publicly).
- ISUP 5.0 wire format (binary, proprietary). Implementing either from observation would be
  reverse engineering; **nothing was implemented**.

### Conclusions

- **PushSDK** is the closest fit for "device → central HRMS server without a local agent", but it
  cannot be implemented responsibly without Hikvision's Push SDK documentation (TPP partner access).
- **ISUP 5.0** needs Hikvision's ISUP SDK (native libraries) in a **central gateway service**; Laravel
  cannot speak it directly. The gateway would forward normalized events to Laravel over an
  authenticated API. The existing HMAC-signed connector endpoint (`POST /attendance/connector/events`)
  already provides that interface (device must then use connection method `connector`), or a
  dedicated endpoint that reuses `DeviceStreamService` would keep Hikvision classification in one place.
- Until documentation is obtained, **alertStream (primary) + polling (recovery)** remain the working
  methods; both need the HRMS to reach the device (same LAN or VPN).

### Possible device test (not done; needs approval)

Passive transport identification only: set `PUSHCfg` to `192.168.88.217`, HTTP port e.g. 8020,
WebSocket port 8021, new username/password, and log incoming connection attempts (request line,
header names such as `Upgrade` / `Sec-WebSocket-Protocol`) without implementing the protocol.
This overwrites the current `210.4.122.139` settings, whose password cannot be read back. Rollback
needs the original password from whoever configured it, or accepting a blank/new one.


## 10. PushSDK live observation (2026-10-31, device pointed at the Mac by the owner via the device UI)

Setup: PushSDK on the device set to server `192.168.88.217`, protocol HTTP, HTTP port 8020, WebSocket
port 8021 (entered in the device web UI by the owner; the UI requires re-typing the password on every
save, so it cannot be changed safely by API without the password). A passive listener on the Mac
accepted only `192.168.88.215`, never replied, and logged structure only. ISUP was not changed.

Observed (verified):

- The **device initiates** the connection (outbound TCP to the configured server, port 8020).
- First and only request, repeated: plain **HTTP/1.1**, not TLS, not MQTT:
  `POST /iot/FX6596310/global/0-global/model/service/operate/PUSH/AuthInfo`
  headers `Host, Accept, Content-Type: application/json, Content-Length: 0` (empty body, **no
  Authorization header**; `FX6596310` is the device's short serial).
- With no reply it times out after **5 s** and retries about **3 s** later (15 attempts in ~2 min).
- It **never connected to the WebSocket port** and sent **no event** for two real scans made during
  the test (17:11:22 check-in, 17:12:01 check-out); those arrived in the HRMS via alertStream in ~1 s.
- Conclusion: PushSDK requires the server to answer `AuthInfo` (presumably with authentication /
  connection information) before anything else happens.

Interpretation (from public Hikvision material, not verified on the device):

- The `/iot/.../global/0-global/model/service/operate/...` path matches Hikvision's **OTAP** ("Open
  Things Access Protocol", Hikvision's IoT protocol: events, attributes, operations; MQTT, HTTP(S)
  and others). This firmware's "PushSDK" is most likely **OTAP Push mode**. Its developer guide
  (`ISAPI_OTAP` on tpp.hikvision.com) requires a TPP license agreement.
- What the `AuthInfo` response must contain, how events are then delivered (MQTT over the WebSocket
  port or HTTP callbacks), and acknowledgement/replay semantics are **unknown** until the
  documentation is available. No reply was implemented.

When requesting documentation from Hikvision TPP, ask specifically for the **OTAP / Push SDK
(PUSH mode) developer guide for access control terminals (DS-K1T321MFX, firmware V4.61.0)**,
including the `PUSH/AuthInfo` exchange and event delivery.

## 11. HTTP Listening retest with a standalone receiver (2026-10-31, 17:24-17:35) — **works**

Setup: HTTP host slot 1 → `http://192.168.88.217:8022/hikvision/http-listening`, protocol HTTP,
format JSON, no device-side auth, heartbeat 30 s, `eventMode=list`, minor `0x26` (attendance only).
Receiver: standalone PHP built-in server (not Laravel, writes nothing to the HRMS), only
`192.168.88.215` accepted, logs safe metadata only; reply format switchable per request.
PushSDK, ISUP, alertStream and polling were not changed (alertStream and polling kept running).

Request format (observed): `POST /hikvision/http-listening`, `multipart/form-data`, headers
`Host, Accept, Content-Type, Connection, Content-Length`; one part named `AccessControllerEvent`,
`application/json` (~750 B for events, 274 B for heartbeats); **no picture parts** with this
subscription. Event JSON: `eventType=AccessControllerEvent`, `dateTime` with offset,
`AccessControllerEvent{majorEventType 5, subEventType 38, serialNo, employeeNoString,
attendanceStatus, currentVerifyMode, ...}`; no `currentEvent` field on this path. Heartbeats:
`eventType=heartBeat` every 30 s.

Acknowledgement experiments (one at a time, observed):

| Reply | Serial(s) | Result |
|---|---|---|
| 200 + ISAPI XML `ResponseStatus` (`statusCode` 1, Content-Length) | 25, then backlog through 669 | accepted; next serial sent, none repeated |
| 200 `text/plain` `OK` (Content-Length) | 672 | accepted (delivered once) |
| 200 `text/html` `OK` (no explicit Content-Length) | 675 | accepted (delivered once) |
| 500 `text/plain` | 678 | **re-sent ~1/s** (47× in ~50 s) until a 200 reply; then accepted, queue continued |

So a prompt 2xx is the acknowledgement; non-2xx makes the device retry the same event about once
per second, holding the queue. Why the earlier Laravel receiver (section 5) saw serial 25 repeated
despite logging "200" is **not explained** (it logged before the response was sent; the HRMS answers
that route in 15-35 ms, so slowness is unlikely).

Backlog and live delivery (observed): on configuration the device drained its stored code-38 log
**in serial order, each once** (25, 102, 104, … 611, 617, 619, 620, 624, 627, 646, 652, 658, 661)
in ~2.5 minutes, then delivered live scans: 17:28:04 check-in (serial 666) at 17:28:05, 17:28:16
check-out (669) at 17:28:17 (~1 s); identical serial, time, status and device user to alertStream.
Statuses seen: checkIn, checkOut, breakIn, breakOut.

Receiver outage (observed): receiver stopped 17:33:17; scan at 17:33:33 (serial 681); receiver
restarted 17:34:30; serial 681 delivered at 17:34:31; already acknowledged events not re-sent.

Production notes: reply 200 quickly (ISAPI `ResponseStatus` XML is a safe choice) after the event
is safely stored or recognized as a duplicate; reply non-2xx only when the device should retry.
Expect a full backlog drain when a slot is (re)configured — the HRMS history guard must skip
records behind the polling cursor. The existing Laravel receiver (`HikvisionPushController`) does
this but must be re-tested on hardware; use a dedicated LAN address/port and no secrets in the URL
logged by web servers (or the device's basic/digest auth). Device-to-server push means the HRMS must
be reachable from the device (same LAN, VPN, or a public HTTPS endpoint with access controls).

## 12. Laravel HTTP Listening receiver on hardware (2026-10-31, 17:40-17:51) — **works**

Receiver: the real Laravel route `POST /attendance/push/hikvision/{token}`
(`HikvisionPushController` → `DeviceStreamService::handle(…, 'push')` → `DeviceSyncService::receivePush`
→ `EventIngestionService`), served by `php -S 192.168.88.217:8022 …/server.php` with
`DB_DATABASE=general_hrms_cleanup_scratch` for that process only (not `artisan serve`, which drops env
overrides that also exist in `.env`; console output discarded because it prints the token URL). A test
device record (id 6) with the real host/serial, copied encrypted credentials and two mappings was
created in the scratch DB; the main app, alertStream and polling kept running on `general_hrms`.

Code changes for this test: the controller records processing stages on the request and returns the
ISAPI `ResponseStatus` XML with an explicit `Content-Length` only after the event is stored, recognized
as a duplicate or deliberately skipped (exceptions → 500 → device retries); new terminable middleware
`App\Http\Middleware\LogDevicePushRequests` logs arrival and, after the response is sent, the actual
status, duration, outcome, serial, event time and response header names (no URL/token/payload), and
disables Debugbar on this route.

Results (observed):

| Test | Result |
|---|---|
| Heartbeats | every 30 s, `outcome=ignored`, **200 sent** in 8-24 ms |
| Live check-in 17:47:12 (serial 684) | stored, 200 sent after 110 ms (~1 s scan→stored) |
| Live check-out 17:47:30 (687) | stored, 200 after 71 ms |
| Live check-in 17:47:43 (690, second user) | stored, 200 after 57 ms |
| Live break-out 17:47:55 (693) | stored, 200 after 55 ms |
| Outage + duplicate: receiver down, check-out 17:48:33 (696), event pulled into the scratch DB by polling, receiver restarted | device delivered 696 one second after restart; Laravel `outcome=duplicate`, **200 sent**; delivered once; one row |
| Re-sends of acknowledged events | none |
| Rows in scratch DB for the test device | 5 (684, 687, 690, 693 via push; 696 via poll) — each exactly once, mapped to the test employees |
| Alert stream (`general_hrms`) | same serials, raw timestamps, statuses and device users |
| Backlog/history filter | not triggered on hardware this round (the device kept its delivery position when only the URL changed or the slot was briefly reset); covered by automated tests |

The section 5 retry of serial 25 was not reproduced. Differences between then and now: Debugbar was
active on the route (now disabled there), the reply was `text/html` `OK` without explicit
Content-Length (now ISAPI XML with Content-Length), and the status was logged before sending (now after).
The standalone tests showed body/length alone don't cause retries, so Debugbar or an unlogged non-2xx
is the most likely cause, **not proven**.

Current state: slot 1 → the Laravel receiver on `192.168.88.217:8022` (scratch DB, token stored only
there). For real use the receiver must run as part of the deployed app (main database), with its own
token, on an address the device can reach.

## 13. HTTP Listening as the primary path; polling as recovery (2026-10-31)

- Scheduling: `attendance:sync-devices` every minute pulls only devices without a healthy push channel
  (no heartbeat/event within 120 s); push-healthy devices get one catch-up pull per hour; the 24 h
  re-read runs daily at 02:30 instead of hourly; `--force` pulls immediately.
- Pull path: pages are ingested as they arrive (memory flat), one duplicate query per page, attendance
  days recalculated once per pull; failures never advance the cursor.
- Measured (benchmark, simulated device): 3,000-event pull 9.7 MB → 1.8 MB peak, 9,008 → 6,108 queries;
  re-read of 3,000 stored events 107 queries (was ≈ 3,000).
- Verified with the real device's heartbeats: the scheduled run skipped the push-healthy device.
- Full write-up for reuse: `docs/integrations/HIKVISION_HTTP_LISTENING_LARAVEL_GUIDE.md` (+ PDF).
- Device state after testing: HTTP Listening slot 1 restored to its original configuration (verified
  identical); slot 2 untouched; PushSDK/ISUP left as at the end of their own tests.
