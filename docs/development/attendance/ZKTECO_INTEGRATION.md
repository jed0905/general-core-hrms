# ZKTeco Integration

Adapter: `app/Services/Attendance/Devices/Adapters/ZktecoAdmsAdapter.php` (vendor `zkteco`).
Receiver: `app/Http/Controllers/Device/ZktecoPushController.php`.

> **Status: PUSH (ADMS) receiver implemented from public protocol descriptions; not verified on
> ZKTeco hardware.** TCP/SDK communication is **not implemented**.

## 1. Protocol research

| Method | Documentation found | Decision |
|---|---|---|
| **HTTP PUSH / ADMS** (`/iclock/cdata`, `/iclock/getrequest`) | ZKTeco "General Introduction of PUSH SDK Protocol" overview (https://cdn.tvc.mx/media/92189/General-Introduction-of-PUSH-SDK-Protocol.pdf; distributor-hosted, 2012), ZKTeco support attachment describing PUSH as HTTP-based; endpoint and ATTLOG format confirmed by several independent open-source servers (e.g. https://deepwiki.com/saifulcoder/adms-server-ZKTeco/2.1-device-communication-system). The full PUSH SDK specification is not public. | **Implemented** (attendance logs only). |
| TCP "standalone SDK" protocol (port 4370) / Windows `zkemkeeper` SDK | Windows SDK is distributed by ZKTeco; the wire protocol is not publicly documented (open-source PHP/Python libraries are reverse-engineered). | **Not implemented**: no documented protocol; a Windows COM SDK is not usable from this PHP server. |

## Supported and unsupported methods (summary)

| Method | Supported |
|---|---|
| HTTP PUSH / ADMS (`/iclock/*`), attendance logs (ATTLOG) only | **Yes**, simulator/HTTP tests only; no physical device tested |
| TCP standalone SDK (port 4370), Windows `zkemkeeper` COM SDK | No |
| ZKBio / BioTime / other ZKTeco server software as an intermediary | No (export a CSV and use Raw Logs > Import) |
| USB-drive log export | Indirectly, via CSV import after conversion to the import template |
| Template / photo / user upload (`USERINFO`, `FINGERTMP`, `FACE`, `BIOPHOTO`) | Deliberately not requested and ignored if sent; never stored |

This does not claim compatibility with all ZKTeco devices: only firmware that speaks the PUSH
protocol, and only after the checklist in §4 passes on that model.

## 2. What is implemented

| Request | Behavior |
|---|---|
| `GET /iclock/cdata?SN=…&options=all` | Handshake. Replies with upload options: `TransFlag=TransData AttLog` (attendance logs only, **no user/fingerprint/face templates, no photos**), `Realtime=1`, `TimeZone=<device tz offset>`. |
| `POST /iclock/cdata?SN=…&table=ATTLOG` | Lines `PIN \t YYYY-MM-DD HH:MM:SS \t status \t verify \t workcode …`; status 0–5 → check_in, check_out, break_out, break_in, overtime_in, overtime_out. Naive times are read in the device's configured timezone. Replies `OK: <n>`. Other tables are acknowledged and ignored. |
| `GET /iclock/getrequest?SN=…` | Heartbeat; no commands are sent to the device. |
| `POST /iclock/devicecmd?SN=…` | Acknowledged. |

Devices are identified by **serial number** and must be registered, active, vendor `zkteco`, method
`zk_adms`. ATTLOG has no event serial, so dedupe uses device + PIN + instant + status. Connection
test and pull are reported as *unsupported* (the device initiates all traffic); status comes from
the last contact time.

## 3. Security note

The PUSH protocol carries no signature: anyone who knows a registered serial number could post
logs. Deploy behind HTTPS, restrict `/iclock/*` by source address (VPN / firewall allow-list /
reverse proxy), and monitor unexpected devices in the sync history. See `NETWORK_TOPOLOGIES.md`.

## 4. Adding models / verification checklist

New ZKTeco models need no code change if they speak PUSH: register with vendor ZKTeco, method PUSH,
and the device serial. Verify per model/firmware:

- [ ] Handshake accepted (`options=all`) and `TransFlag` honored (no template uploads).
- [ ] ATTLOG column order (some firmware adds fields) and status codes.
- [ ] Device timezone handling with `TimeZone=`.
- [ ] Behavior with HTTPS (some firmware supports HTTP only; then use VPN or a local reverse proxy).
