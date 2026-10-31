# Network Topologies

| # | Topology | Method | When to use | Requirements |
|---|---|---|---|---|
| 1 | HRMS and devices on the same network (on-premises server, or hosted server with a site-to-site VPN) | HRMS polls (`isapi_pull`), scheduled every 5 minutes by `attendance:sync-devices` | HRMS can open HTTP(S) to the device IP | Firewall rule HRMS → device port; dedicated device account; HTTPS where the device supports it |
| 2 | Hosted HRMS, devices in a private branch LAN, no VPN | **On-premises connector** (`php artisan attendance:connector config.json`) | The hosted server can't reach private IPs (never assume it can) | One always-on machine in the LAN running the HRMS codebase; outbound HTTPS to the HRMS only; device credentials stay in the local config file |
| 3 | Device sends events to the HRMS | Hikvision HTTP listening (`/attendance/push/hikvision/{token}`), ZKTeco PUSH (`/iclock/*`) | Device can reach the HRMS URL | HTTPS strongly recommended; per-device secret token (Hikvision) or registered serial + source allow-list (ZKTeco) |
| 4 | No connectivity | CSV import / manual entry | Exports from a device or a paper log | Import permission; entries are labelled as imported / manual |

## Security requirements

- **Never expose a biometric device directly to the internet** (no port forwarding to the device).
  Use a VPN, the connector (outbound only), or device push to the HRMS over HTTPS.
- Device push endpoints are stateless (no session/CSRF), rate-limited per route, and authenticated
  per device. Replays are harmless because ingestion is idempotent.
- Connector requests are HMAC-SHA256 signed over timestamp, nonce and body; timestamps outside
  ±300 s and reused nonces are rejected; a connector may only submit for devices assigned to it.
- ZKTeco PUSH has no request signature: restrict `/iclock/*` to known source addresses at the
  firewall or reverse proxy.
- Keep device clocks on NTP; the device page shows the clock drift measured at the last test.

## Connector configuration

```json
{
  "hrms_url": "https://hrms.example.com",
  "connector_key": "conn_…",
  "connector_secret": "…",
  "spool_dir": "/var/lib/hrms-connector",
  "devices": [
    {"hrms_device_id": 12, "vendor": "hikvision", "connection_method": "isapi_pull",
     "host": "192.168.10.20", "port": 80, "use_tls": false, "verify_tls": true,
     "username": "attendance", "password": "…", "timezone": "Asia/Manila", "page_size": 30}
  ]
}
```

In the HRMS, register each device with connection method *On-premises HRMS connector* and assign
the connector. Run the connector as a service (systemd / Task Scheduler) with
`--interval=60`. Batches are spooled on disk until the HRMS accepts them; events already spooled
are not spooled again. Protect the config file (it holds device passwords and the connector secret).
