# Hikvision DS-K1T321 Integration

Adapter: `app/Services/Attendance/Devices/Adapters/HikvisionIsapiAdapter.php` (vendor `hikvision`).

> **Status: implemented from the customer's tested integration; NOT re-verified on hardware in
> this session.** Automated tests use HTTP fakes shaped like the tested responses. They prove the
> adapter's handling, not compatibility with any device or firmware.

## 1. What was found (branch `feature/hikvision-api`, commit `3203d23`, 2026-09-02)

| Question | Answer from the existing code |
|---|---|
| How was the device reached? | Directly over the LAN by IP (`HIKVISION_IP`, port 80, `http`), from the Laravel server. |
| Protocol | Hikvision **ISAPI** (HTTP + JSON, `?format=json`), via the third-party package `shaykhnazar/hikvision-isapi` ^1.6 (HTTP digest authentication). |
| Endpoints | `GET /ISAPI/System/deviceInfo` (online check, `DeviceInfo.model`), `POST /ISAPI/AccessControl/AcsEvent` (event search). |
| Authentication | Username/password from `.env` (digest). |
| Event retrieval | **Polling** by time window: `AcsEventCond{searchID, searchResultPosition: 0, maxResults: 30, major: 5, minor: 38, startTime, endTime, timeReverseOrder: true}`. |
| Timestamps | ISO 8601 with offset in `time` (e.g. `2026-09-02T20:15:00+08:00`). |
| Employee identifier | `employeeNoString` (the person's ID on the device); also `name`, `cardNo`. |
| Status | `attendanceStatus`: `checkIn`, `checkOut`, `breakIn`, `breakOut`, `overtimeIn`, `overtimeOut`. |
| Push | A webhook controller parsed multipart `AccessControllerEvent` (or `event_log`) JSON with `eventType` `AccessControllerEvent` / `heartBeat`, `serialNo`, `currentVerifyMode`, etc. It was **not routed**. |
| Firmware | **Not recorded.** |
| Limitations seen in the code | Only the first 30 results (no pagination); a hard-coded employee `220464`; no dedupe beyond time/direction; full payloads logged; unauthenticated `GET /api/hikvision/sync-test`; `dd()` in `DashboardController`. |

Do not deploy that branch: it writes to `employee_attendance_logs`, which no longer exists. It is kept
only as a reference.

## 2. What the new adapter does

| Operation | Implementation |
|---|---|
| Credentials | Per device, Laravel-encrypted columns; never serialized, logged or shown. HTTP digest via Laravel's HTTP client (no extra package). |
| Test connection | `GET /ISAPI/System/deviceInfo?format=json` → model, serial, firmware; optional `GET /ISAPI/System/time?format=json` → clock drift (not every firmware exposes it; failure is ignored). |
| Retrieve events | `POST /ISAPI/AccessControl/AcsEvent?format=json` per configured minor code; window in the device timezone (ISO 8601 with offset); **pagination** while `responseStatusStrg = MORE` (position += `numOfMatches`); single-object `InfoList` handled; page size per device (default 30, as tested). |
| Event filter | `major 5`, `minor 38` by default (as tested). Other minors (e.g. face or card authentication) can be added per device in `settings.acs_event_minors`; codes must be confirmed for the firmware. |
| Incremental | Cursor = latest event instant; each pull re-reads `attendance.sync.overlap_minutes` (default 24 h) before it, so events stored late by the device are caught; ingestion dedupes on device + `serialNo` + instant. |
| Push (HTTP listening) | `POST /attendance/push/hikvision/{token}`; per-device random token (stored as SHA-256). Accepts multipart `AccessControllerEvent`/`event_log` or a JSON body; `heartBeat` updates last contact. |
| Data kept | `serialNo`, major/minor, `attendanceStatus`, `currentVerifyMode`. **Not kept:** `name`, `cardNo`, pictures. |
| Errors | 401 → `auth_failed` (not retried); timeouts/refused → retried up to 3 times; other HTTP errors report only `statusString`/`subStatusCode`. |

## 3. Device setup checklist (to verify on site)

1. Record model and **firmware version** (Device page → Test connection stores them).
2. Create a dedicated ISAPI user on the device (not `admin` where possible) and enable ISAPI over HTTP/HTTPS.
3. Set the device clock and time zone (NTP preferred); check the drift shown after Test connection.
4. Register the device in HRMS (vendor Hikvision, method *HRMS polls* for same network/VPN, *pushes* for HTTP listening, *connector* for hosted HRMS + private LAN).
5. Enroll faces/fingerprints **on the device**; map each person's device employee number to the HRMS employee (Enrollments).
6. Run *Synchronize now*; confirm events and that `attendanceStatus` values are populated (if the device isn't in attendance mode, statuses arrive as `unknown` and pairing falls back to alternating in/out).
7. If face or card authentications are missing, find their minor codes in the device's event log/ISAPI capability and add them to the device's minor codes.
8. For push: set the HTTP listening host to the URL shown on the device page (HTTPS strongly recommended).

## 4. Hardware verification still required

- [ ] Pull with pagination over > 1 page on the customer's DS-K1T321 firmware.
- [ ] `GET /ISAPI/System/time` availability.
- [ ] Minor codes for face / card / fingerprint authentication on this firmware.
- [ ] HTTP listening payload shape (multipart field name) on this firmware.
- [ ] HTTPS with the device's certificate (`verify_tls`).
- [ ] Behavior when the device's event log wraps or `serialNo` resets.

Hardware tests are not part of CI. Run them only against a real device on a test network.
Full ISAPI documentation is partner-gated (https://partner.hikvision.com/tpp/OpenCapabilities);
community notes report `maxResults` limits from 10 to 30 depending on model.

## 5. Verification status by item

| Item | Status | Evidence |
|---|---|---|
| Digest authentication to ISAPI | Verified by the earlier integration on a real DS-K1T321 (firmware not recorded) | `feature/hikvision-api` @ `3203d23` used digest auth through `shaykhnazar/hikvision-isapi` |
| `POST /ISAPI/AccessControl/AcsEvent?format=json` request/response fields | Verified by the earlier integration (same unit) | Field names reused verbatim: `AcsEventCond`, `InfoList[].serialNo/time/employeeNoString/attendanceStatus` |
| Event codes major 5 / minor 38 | Verified for that unit only | Other authentication methods (face, card) use other minor codes; configurable per device, **needs physical testing** |
| Pagination (`searchResultPosition`, `responseStatusStrg = MORE`) | Simulator / HTTP-fake tests only | Earlier integration never paged past 30; **needs physical testing** |
| Incremental sync (cursor + overlap window, dedupe by `serialNo`) | Simulator / HTTP-fake tests only | `DeviceIngestionTest`, `DeviceProtocolTest` |
| Timestamps with offset; device timezone fallback; DST-safe UTC storage | Unit and feature tests | `EventTimeTest`, `AttendanceEngineTest` |
| Network failures, retries (3 attempts, backoff), resume from cursor | Simulator tests | `DeviceIngestionTest` |
| Duplicate prevention (unique `dedupe_key`) | Tests | `DeviceIngestionTest` |
| Device push (HTTP listening, per-device secret token in URL) | HTTP tests with the payload shape from the earlier webhook code (which was never routed) | **Needs physical testing** (multipart field name, firmware differences) |
| `GET /ISAPI/System/deviceInfo`, `GET /ISAPI/System/time` | HTTP-fake tests | `deviceInfo` used by the earlier integration; `time` **not verified** |

Do not assume another firmware version or model behaves like the tested unit.

## 6. Physical test: DS-K1T321MFX, firmware V4.61.0 build 260828 (2026-10-31, local HRMS)

Verified on the real device through the HRMS (HTTP, port 80, digest authentication, device clock on
NTP, UTC+08:00 reported as `Asia/Shanghai`; device registered with timezone `Asia/Manila`):

| Item | Result |
|---|---|
| Digest authentication, `GET /ISAPI/System/deviceInfo` | ✅ Works. This firmware answers **without a JSON `DeviceInfo` section** even with `?format=json` (inferred XML); the adapter now accepts XML. Reported model `DS-K1T321`. |
| `GET /ISAPI/System/time` | ✅ Works (clock drift 0 s). |
| `POST /ISAPI/AccessControl/AcsEvent?format=json` | ✅ Works, JSON. |
| Pagination | ✅ `responseStatusStrg` `MORE` → `OK`; 616 records over 21 pages of 30 (direct), and 29 minor-38 records over 6 pages of 5 through the adapter. |
| Timestamps | ✅ `2026-10-31T12:33:45+08:00` kept raw, stored as `04:33:45Z`; the `Asia/Shanghai` identifier has no effect because the offset is used. |
| Duplicates | ✅ Repeated pulls of the same window store nothing new (`serialNo` + instant). |
| Unknown employee numbers | ✅ Stored unmapped; matched after an Enrollments mapping. |
| Automatic sync | ✅ Every minute through `schedule:work`; see `OPERATIONS.md`. |

Event codes observed on this unit (counts from the device's own log; meanings not assumed):

| major/minor | Records | With employee number | `attendanceStatus` / `label` seen | Other observation |
|---|---|---|---|---|
| 5/38 | 29 | 29 | `checkIn`/Check In (9), `checkOut`/Check Out (3), `breakIn`/Break In (2), none (15) | never carries `FaceRect` |
| 5/75 | 14 | 14 | none | every record carries `FaceRect` (face detected); not collected by default |
| 5/1, 5/104 | 1 each | 1 each | none | meaning unconfirmed |
| majors 1, 2, 3 and other 5/x | 572 | 0 | - | door, alarm, operation events; never requested or stored as punches |

Not yet observed: `breakOut`, overtime statuses, and which code a fingerprint or card scan produces;
`currentVerifyMode` reports the configured mode (`faceOrFpOrCardOrPw`), not the method used. If the
unit's punches arrive under another minor (e.g. 75), add it in the device page's *Event minor codes*.

## 7. Event streaming on the DS-K1T321MFX (V4.61.0 build 260828, 2026-10-31)

| Interface | Result on this unit |
|---|---|
| `GET /ISAPI/System/capabilities` | 200 XML; `isSupportSubscribeEvent=true`, `isSupportHttps=true` |
| `GET /ISAPI/Event/notification/subscribeEventCap` | 200 XML; heartbeat 1-30 s, `AccessControllerEvent` minor events include `0x26` (38) and `0x4b` (75); `LocalUserChange` |
| `GET /ISAPI/Event/notification/httpHosts` | 200 XML; 2 slots: #1 HTTP/XML to `0.0.0.0:80` (unconfigured), #2 EHome (unconfigured). **Not changed.** |
| `GET /ISAPI/Event/capabilities` | 404 `notSupport` |
| `POST /ISAPI/Event/notification/subscribeEvent` | Not tried (alertStream was sufficient) |
| `GET /ISAPI/Event/notification/alertStream` | ✅ Digest 401 challenge, then 200 `multipart/mixed; boundary=MIME_boundary`; parts named `AccessControllerEvent`, JSON with `Content-Length`; stays open |

Observed alertStream behavior:

- On connect the device replays stored records not yet delivered on a stream, `currentEvent: false`,
  about 4 records per second, continuing from where the previous connection stopped (`serialNo`).
- No heartbeat parts were seen, during replay or when idle; idle connections are recycled by the listener.
- Attendance parts (`majorEventType` 5, `subEventType` 38) carry `employeeNoString`, `attendanceStatus`,
  `label`, `serialNo`, `currentVerifyMode`, `dateTime` with offset; face parts (75) carry `FaceRect`.
  Names, card numbers and face rectangles are never stored.
- A real scan (15:44:16, Break In, serial 646) was stored through the stream at 15:44:17 (≈1 s); the
  next scheduled poll retrieved it as a duplicate (one row). `breakOut` was also observed (13:31:17,
  via polling).
- Guzzle's streaming handler cannot do digest authentication; the listener uses ext-curl directly.

Not yet verified on hardware: behavior when the device itself loses network mid-stream and whether
events scanned during that outage are replayed by the stream or only recovered by polling.

## 8. HTTP host notification (push) on the DS-K1T321MFX (2026-10-31)

Configuration API: `GET/PUT /ISAPI/Event/notification/httpHosts/<id>` (XML). 2 slots; protocol HTTP,
HTTPS or EHome; format XML or JSON; addressing IP or hostname; port 0-65535; URL up to 128
characters; device-side authentication none, basic or MD5 digest (user name 1-32, password 8-16
characters); subscription heartbeat 1-30 s, `eventMode` all or list. A PUT must contain the event
element exactly as the device returns it (empty `minorAlarm`, `minorException`, `minorOperation`
included), otherwise it answers 400 `MessageParametersLack`. `/httpHosts/1/test` is not supported.

Test (slot 1 → `http://<mac>:8011/attendance/push/hikvision/<token>`, JSON, list with minor 0x26):

- The device posted within seconds: `multipart/form-data` with one part `AccessControllerEvent` (JSON),
  from its own IP; the receiver read it.
- It first sent its **stored log** (starting at serial 25, 2025-11-07), not live events, and kept
  re-sending the same record about once per second although the receiver answered `200 OK`: the
  response is not accepted as an acknowledgement. The acknowledgement format the firmware expects is
  not known (partner documentation).
- One historical record (serial 25) was stored before this was noticed (event id 9, unmapped, type
  unknown). Push now skips anything behind the polling cursor, like stream replays.
- `php artisan serve` prints request paths, so a token in the URL appears in its console output; the
  token was revoked and slot 1 restored to the saved original (verified identical).

Status: **push is not working yet**; no live scan was received through it. Restore: PUT the saved file
`storage/app/device-config-backups/hikvision-1-httpHosts-1-original-*.xml` to
`/ISAPI/Event/notification/httpHosts/1` (done; slot 2 never changed).


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

