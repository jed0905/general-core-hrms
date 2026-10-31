# Blockers

Items that need a human decision or external input. Work continued on everything independent of them.

| # | Blocker | Impact | Needed to unblock |
|---|---|---|---|
| B1 | No attendance/DTR engine computes late, undertime, absences, overtime or holiday/night hours. | Payroll cannot pull these automatically. | A DTR module, or keep using approved payroll time inputs (shipped). Not blocking the payroll module. |
| B2 | `holidays` has no country/jurisdiction. | `working_days` proration uses one global holiday list; holiday premium categories must be supplied as time inputs. | A jurisdiction column on holidays (Time module change, needs approval). |
| B3 | Philippine statutory values (SSS schedule, PhilHealth rate/floor/ceiling, Pag-IBIG rates/MFS, BIR withholding tables per frequency, benefits ceiling, DOLE premium multipliers, regional minimum wages) are **not entered**. Official sources were located (see `docs/development/payroll/PH_LOCALIZATION.md` §5) but values must be read from the documents and verified by a person, not by the agent. | PH runs calculate as previews only with draft rules and cannot be finalized until rule versions are entered and verified. | Payroll staff/adviser enter each rule version from the cited issuance; a second user verifies it. |
| B4 | BIR 1601-C, 1604-C/alphalist, BIR 2316, SSS R-3/R-5, PhilHealth RF-1 and Pag-IBIG MCRF file layouts were not verified against the agencies' current specifications. | Only generic contribution/tax reports are provided (Excel/PDF), not agency upload files. | Confirm current layouts and submission channels on the agencies' official sites. |
| B5 | Year-end tax annualization/adjustment, automatic 13th month generation, and statutory leave benefit differentials are not implemented for PH. | Must be handled with adjustments. | Specification + verified rules. |
| A-B1 | ~~Legacy attendance system.~~ **Resolved 2026-10-31**: removed, tables dropped by `000006`. Only check left: nothing outside this repo (e.g. a deployment of `feature/hikvision-api`) may still write to the dropped table. | Such a writer would fail after migration. | Deployment check (`attendance/PRE_COMMIT_AUDIT.md` §7). |
| A-B2 | No Hikvision or ZKTeco hardware in this session; DS-K1T321 firmware of the earlier test was not recorded. | Adapters are verified only against simulated/faked protocol shapes. | Run the hardware checklists (`HIKVISION_DS_K1T321.md` §4, `ZKTECO_INTEGRATION.md` §4) on site. |
| A-B3 | Full Hikvision ISAPI and ZKTeco PUSH specifications are vendor/partner-gated. | Event minor codes, page limits and ATTLOG variants may differ by model/firmware. | Partner documentation or on-device capability checks. |
| A-B4 | Whether biometric attendance data is "sensitive personal information" under RA 10173 was not confirmed from an NPC source; no attendance retention period is defined. | No purge job; customer must document lawful basis/PIA/retention. | Customer DPO/counsel decision. |
| A-B5 | MySQL concurrency test for simultaneous sync/push of the same device was not run (a scratch MySQL database was used for migrations only). | Idempotency relies on the unique `dedupe_key` (tested on SQLite) and a cache lock per device. | Approval to run `tests/Concurrency`-style worker tests on a `_concurrency_test` database. |
| A-B6 | No retention/purge mechanism; no retention periods approved. | Raw events, mappings and audit logs are kept indefinitely. | DPO-approved schedule; then the purge design in `attendance/SECURITY_AND_PRIVACY.md`. |
| A-B7 | The removed `CheckTardinessJob` sent tardiness e-mails (it was broken: missing `TardinessNotification` model). No attendance notification replaces it. | No automatic lateness e-mails. | Decide whether notifications are wanted (event on day calculation). |
| A-B8 | ~~Dropping the legacy tables.~~ **Resolved 2026-10-31** (authorized; migration `000006`). | - | - |

