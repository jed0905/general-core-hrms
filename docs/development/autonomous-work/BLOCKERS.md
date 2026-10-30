# Blockers

Items that need a human decision or external input. Work continued on everything independent of them.

| # | Blocker | Impact | Needed to unblock |
|---|---|---|---|
| B1 | No attendance/DTR engine computes late, undertime, absences, overtime or holiday/night hours. | Payroll cannot pull these automatically. | A DTR module, or keep using approved payroll time inputs (shipped). Not blocking the payroll module. |
| B2 | `holidays` has no country/jurisdiction. | `working_days` proration uses one global holiday list; holiday premium categories must be supplied as time inputs. | A jurisdiction column on holidays (Time module change, needs approval). |
| B3 | Philippine statutory values (SSS schedule, PhilHealth rate/floor/ceiling, Pag-IBIG rates/MFS, BIR withholding tables per frequency, benefits ceiling, DOLE premium multipliers, regional minimum wages) are **not entered**. Official sources were located (see `docs/development/payroll/PH_LOCALIZATION.md` §5) but values must be read from the documents and verified by a person, not by the agent. | PH runs calculate as previews only with draft rules and cannot be finalized until rule versions are entered and verified. | Payroll staff/adviser enter each rule version from the cited issuance; a second user verifies it. |
| B4 | BIR 1601-C, 1604-C/alphalist, BIR 2316, SSS R-3/R-5, PhilHealth RF-1 and Pag-IBIG MCRF file layouts were not verified against the agencies' current specifications. | Only generic contribution/tax reports are provided (Excel/PDF), not agency upload files. | Confirm current layouts and submission channels on the agencies' official sites. |
| B5 | Year-end tax annualization/adjustment, automatic 13th month generation, and statutory leave benefit differentials are not implemented for PH. | Must be handled with adjustments. | Specification + verified rules. |
